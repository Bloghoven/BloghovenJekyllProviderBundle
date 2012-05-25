<?php

namespace Bloghoven\Bundle\JekyllProviderBundle\Entity;

use Bloghoven\Bundle\BlogBundle\ContentProvider\Interfaces\ImmutableCategoryInterface;

use Bloghoven\Bundle\JekyllProviderBundle\ContentProvider\JekyllContentProvider;

use Symfony\Component\Finder\Finder;

use Gaufrette\Path;

/**
* 
*/
class Category implements ImmutableCategoryInterface
{
  protected $name;

  public function __construct($name)
  {
    $this->name = $name;
  }

  public function getParent()
  {
    return null;
  }

  public function getName()
  {
    return $this->name;
  }

  public function getPermalinkId()
  {
    return $this->name;
  }

  public function getChildren()
  {
    $categories = array();

    return array();
  }
}