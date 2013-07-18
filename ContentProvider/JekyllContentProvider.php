<?php

namespace Bloghoven\Bundle\JekyllProviderBundle\ContentProvider;

use Bloghoven\Bundle\JekyllProviderBundle\Entity\Entry;
use Bloghoven\Bundle\JekyllProviderBundle\Entity\Category;

use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\CachableContentProviderInterface;
use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ImmutableCategoryInterface;

use Symfony\Component\Stopwatch\Stopwatch;

use Doctrine\Common\Cache\Cache;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;

use Gaufrette\Filesystem;
use Gaufrette\Util\Path;
use Gaufrette\StreamWrapper;

class JekyllContentProvider implements CachableContentProviderInterface
{
  protected $filesystem;
  protected $file_extension;
  protected $depth;
  protected $stopwatch;

  protected $entry_keys;

  protected $cache;

  public function __construct(Filesystem $filesystem, $file_extension = 'md', $depth = 0)
  {
    $this->filesystem = $filesystem;
    $this->file_extension = $file_extension;
    $this->depth = (int)$depth;
  }

  public function setStopwatch(Stopwatch $stopwatch = null)
  {
    $this->stopwatch = $stopwatch;
  }

  public function setCache(Cache $cache = null)
  {
    $this->cache = $cache;
  }

  public function getCache()
  {
    return $this->cache;
  }

  public function hasCache()
  {
    return $this->cache !== null;
  }

  public function getFilesystem()
  {
    return $this->filesystem;
  }

  public function getLastModificationTime()
  {
    $keys = $this->getUnfilteredEntryKeys();

    $last_date = 0;

    foreach ($keys as $key)
    {
      $mtime = $this->filesystem->mtime($key);
      if ($mtime > $last_date)
      {
        $last_date = $mtime;
      }
    }

    return \DateTime::createFromFormat('U', $last_date);
  }

  protected function validatePermalinkId($permalink_id)
  {
    if (strpos($permalink_id, '..') !== false)
    {
      throw new \RuntimeException("Permalinks with double dots are not allowed with the current provider, and are always advised against.");
    }
  }

  public function getFile($file_key)
  {
    return $this->filesystem->get($file_key);
  }

  /* ------------------ ContentProviderInterface methods ---------------- */

  protected function getUnfilteredEntryKeys()
  {
    if (!$this->entry_keys)
    {
      $stopwatch_event = "jekyllContentProvider.getUnfilteredEntryKeys";

      $this->usingStopWatch(function($stopwatch) use ($stopwatch_event)
      {
        $stopwatch->start($stopwatch_event, 'bloghoven');
      });

      $extension = $this->file_extension;

      $this->entry_keys = array_filter($this->filesystem->keys(), function ($key) use ($extension) {
        $normalized_key = Path::normalize($key);

        $path_info = pathinfo($normalized_key);

        $explosion = explode('/', $path_info['dirname']);

        if (end($explosion) !== '_posts')
        {
          return false;
        }

        if ($path_info['extension'] != $extension)
        {
          return false;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}-.+?\.\w+$/', $path_info['basename']))
        {
          return false;
        }

        return true;
      });

      $this->usingStopWatch(function($stopwatch) use ($stopwatch_event)
      {
        $stopwatch->stop($stopwatch_event);
      });
    }

    return $this->entry_keys;
  }

  protected function usingStopwatch($callable)
  {
    if ($this->stopwatch)
    {
      $callable($this->stopwatch);
    }
  }

  protected function getUnfilteredEntries()
  {
    $stopwatch_event = "jekyllContentProvider.getUnfilteredEntries";

    $this->usingStopWatch(function($stopwatch) use ($stopwatch_event)
    {
      $stopwatch->start($stopwatch_event, 'bloghoven');
    });

    $self = $this;
    $entries = array_map(function($key) use (&$self)
    {
      return new Entry($self->getFile($key), $self);
    }, $this->getUnfilteredEntryKeys());

    $this->usingStopWatch(function($stopwatch) use ($stopwatch_event)
    {
      $stopwatch->stop($stopwatch_event);
    });

    return $entries;
  }

  protected function getPublishedEntries()
  {
    $stopwatch_event = "jekyllContentProvider.getPublishedEntries";

    $this->usingStopWatch(function($stopwatch) use ($stopwatch_event)
    {
      $stopwatch->start($stopwatch_event, 'bloghoven');
    });

    $unfiltered = $this->getUnfilteredEntries();

    $ret = array_filter($unfiltered, function ($entry) {
      return !$entry->isDraft();
    });

    $this->usingStopWatch(function($stopwatch) use ($stopwatch_event)
    {
      $stopwatch->stop($stopwatch_event);
    });

    return $ret;
  }

  public function getHomeEntriesPager()
  {
    $stopwatch_event = "jekyllContentProvider.getHomeEntriesPager";

    $this->usingStopWatch(function($stopwatch) use ($stopwatch_event)
    {
      $stopwatch->start($stopwatch_event, 'bloghoven');
    });

    $filtered = $this->getPublishedEntries();

    $filtered = $this->sortEntries($filtered);

    $ret = new Pagerfanta(new ArrayAdapter($filtered));

    $this->usingStopWatch(function($stopwatch) use ($stopwatch_event)
    {
      $stopwatch->stop($stopwatch_event);
    });

    return $ret;
  }

  public function getEntriesPagerForCategory(ImmutableCategoryInterface $category)
  {
    if (!($category instanceof Category))
    {
      throw new \LogicException("The Jekyll provider only supports categories from the same provider.");
    }

    $entries = array_filter($this->getPublishedEntries(), function ($entry) use ($category)
    {
      return in_array($category, $entry->getCategories());
    });

    $entries = $this->sortEntries($entries);

    return new Pagerfanta(new ArrayAdapter($entries));
  }

  protected function sortEntries($entries)
  {
    usort($entries, function($a, $b) {
      $time_diff = $b->getPostedAt()->getTimestamp() - $a->getPostedAt()->getTimestamp();

      if ($time_diff == 0)
      {
        return strcmp($a->getPathname(), $b->getPathname());
      }
      return $time_diff;
    });

    return $entries;
  }

  protected function getCategories()
  {
    $categories = array();

    foreach ($this->getPublishedEntries() as $entry)
    {
      if (($entry_categories = $entry->getCategories()))
      {
        $categories = array_merge($categories, $entry_categories);
      }
    }

    return array_unique($categories, SORT_REGULAR);
  }

  public function getCategoryRoots()
  {
    return $this->getCategories();
  }

  public function getEntryWithPermalinkId($permalink_id)
  {
    $entries = $this->getPublishedEntries();

    foreach ($entries as $entry)
    {
      if ($entry->getPermalinkId() == $permalink_id)
      {
        return $entry;
      }
    }

    return null;
  }

  public function getCategoryWithPermalinkId($permalink_id)
  {
    $categories = $this->getCategories();

    foreach ($categories as $category)
    {
      if ($category->getPermalinkId() == $permalink_id)
      {
        return $category;
      }
    }

    return null;
  }
}