<?php

namespace Bloghoven\Bundle\JekyllProviderBundle\ContentProvider;

use Bloghoven\Bundle\JekyllProviderBundle\Entity\Entry;
use Bloghoven\Bundle\JekyllProviderBundle\Entity\Category;

use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ContentProviderInterface;
use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ImmutableCategoryInterface;

use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\ArrayAdapter;

use Gaufrette\Filesystem;
use Gaufrette\Path;
use Gaufrette\StreamWrapper;

class JekyllContentProvider implements ContentProviderInterface
{
  protected $filesystem;
  protected $file_extension;
  protected $depth;

  public function __construct(Filesystem $filesystem, $file_extension = 'md', $depth = 0)
  {
    $this->filesystem = $filesystem;
    $this->file_extension = $file_extension;
    $this->depth = (int)$depth;
  }

  public function getFilesystem()
  {
    return $this->filesystem;
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

  protected function getUnfilteredEntries()
  {
    $extension = $this->file_extension;

    $keys = array_filter($this->filesystem->keys(), function ($key) use ($extension) {
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

    $self = $this;

    return array_map(function($key) use (&$self)
    {
      return new Entry($self->getFile($key), $self);
    }, $keys);
  }

  protected function getPublishedEntries()
  {
    $unfiltered = $this->getUnfilteredEntries();

    return array_filter($unfiltered, function ($entry) {
      return !$entry->isDraft();
    });
  }

  public function getHomeEntriesPager()
  {
    $filtered = $this->getPublishedEntries();

    usort($filtered, function($a, $b) {
      return $b->getPostedAt()->getTimestamp() - $a->getPostedAt()->getTimestamp();
    });

    return new Pagerfanta(new ArrayAdapter($filtered));
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

    usort($entries, function($a, $b) {
      return $b->getPostedAt()->getTimestamp() - $a->getPostedAt()->getTimestamp();
    });

    return new Pagerfanta(new ArrayAdapter($entries));
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