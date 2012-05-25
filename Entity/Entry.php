<?php

namespace Bloghoven\Bundle\JekyllProviderBundle\Entity;

use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ImmutableEntryInterface;
use Bloghoven\Bundle\JekyllProviderBundle\ContentProvider\JekyllContentProvider;

use Symfony\Component\Yaml\Yaml as YamlParser;

use Gaufrette\File;
use Gaufrette\Path;

/**
*
*/
class Entry implements ImmutableEntryInterface
{
  protected $file;
  protected $content_provider;

  protected $contents;
  protected $front_matter = array();

  protected $parsed_category_names = array();
  protected $parsed_date;
  protected $parsed_title;


  public function __construct(File $file, JekyllContentProvider $content_provider)
  {
    $this->file = $file;
    $this->content_provider = $content_provider;
  }

  public function getPathname()
  {
    return $this->file->getKey();
  }

  public function getPath()
  {
    return pathinfo($this->file->getKey(), PATHINFO_DIRNAME);
  }


  // ------------------------------------------------------------

  public function getPermalinkId()
  {
    return $this->getPathname();
  }

  public function getTitle()
  {
    $this->parseFilename();
    $this->loadContent();

    if (isset($this->front_matter['title']))
    {
      return $this->front_matter['title'];
    }

    return $this->parsed_title;
  }

  public function getExcerpt()
  {
    return $this->getContent();
  }

  public function getContent()
  {
    $this->loadContent();

    return $this->contents;
  }

  protected function loadContent()
  {
    if ($this->contents === null)
    {
      $content = $this->file->getContent();

      $matches = array();

      if (preg_match("/---(.*?)---(.*)/ms", $content, $matches))
      {
        $unparsed_front_matter = $matches[1];
        $this->front_matter = YamlParser::parse($unparsed_front_matter);
        $this->contents = trim($matches[2]);
      }
      else
      {
        $this->contents = $content;
      }

      $this->contents = preg_replace("/\{\%.*?\%\}/", '', $this->contents);
    }
  }

  protected function parseFilename()
  {
    if ($this->parsed_date === null)
    {
      $matches = array();
      $regex = '/^(?P<categories>.*)_posts\/(?P<date>\d{4}-\d{2}-\d{2})-(?P<title>.+?)\.\w+$/';

      if(!preg_match($regex, $this->getPathname(), $matches))
      {
        throw new \RuntimeException("Filename not according to Jekyll standard");
      }

      if ($matches['categories'] != '')
      {
        $this->parsed_category_names = explode('/', $matches['categories']);
      }

      $this->parsed_date = \DateTime::createFromFormat('Y-m-d', $matches['date']);
      $this->parsed_title = $matches['title'];
    }
  }

  public function getPostedAt()
  {
    $this->parseFilename();
    $this->loadContent();

    if (isset($this->front_matter['date']))
    {
      return \DateTime::createFromFormat('Y-m-d', $this->front_matter['date']);
    }

    return $this->parsed_date;
  }

  public function getModifiedAt()
  {
    return $this->getPostedAt();
  }

  public function isDraft()
  {
    $this->loadContent();

    if (isset($this->front_matter['published']) && !$this->front_matter['published'])
    {
      return true;
    }
    return false;
  }

  public function getCategories()
  {
    $this->parseFilename();
    $this->loadContent();

    $category_names = array();

    if (isset($this->front_matter['category']))
    {
      $category_names[] = $this->front_matter['category'];
    }
    else if (isset($this->front_matter['categories']))
    {
      if (is_array($this->front_matter['categories']))
      {
        $category_names = $this->front_matter['categories'];
      }
      else
      {
        $category_names = explode(' ', (string)$$this->front_matter['categories']);
      }
    }
    else
    {
      $category_names = $this->parsed_category_names;
    }

    if (count($category_names))
    {
      return array_map(function ($category_name) {
        return new Category($category_name);
      }, $category_names);
    }

    return null;
  }

  public function getAttribute($attribute)
  {
    if (isset($this->front_matter[$attribute]))
    {
      return $this->front_matter[$attribute];
    }
    return false;
  }
}