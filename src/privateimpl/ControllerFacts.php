<?hh // strict
/*
 *  Copyright (c) 2016, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

namespace Facebook\HackRouter\PrivateImpl;

use \Facebook\DefinitionFinder\ScannedBasicClass;
use \Facebook\DefinitionFinder\ScannedClass;
use \Facebook\HackRouter\{
  HttpMethod,
  IncludeInUriMap,
  SupportsGetRequests,
  SupportsPostRequests
};

final class ControllerFacts<T as IncludeInUriMap> {
  public function __construct(
    private classname<T> $baseClass,
    private ClassFacts $classFacts,
  ) {
  }

  public function getControllers(
  ): ImmMap<classname<T>, ImmSet<HttpMethod>> {
    $controllers = Map { };
    $subclasses = $this->classFacts->getSubclassesOf($this->baseClass);
    foreach ($subclasses as $name => $def) {
      if (!$this->isUriMappable($def)) {
        continue;
      }
      $controllers[$name] = $this->getHttpMethodsForController($name);
    }
    return $controllers->immutable();
  }

  <<TestsBypassVisibility>>
  private function isUriMappable(
    ScannedClass $class
  ): bool {
    if (!$class instanceof ScannedBasicClass) {
      return false;
    }
    if ($class->isAbstract()) {
      return false;
    }

    $cf = $this->classFacts;
    if (!$cf->doesImplement(IncludeInUriMap::class, $class->getName())) {
      return false;
    }

    // This is also me being opinionated.
    invariant(
      $class->isFinal(),
      'Classes implementing IncludeInUriMap should be abstract or final; '.
      '%s is neither',
      $class->getName(),
    );
    return true;
  }

  <<TestsBypassVisibility>>
  private function getHttpMethodsForController(
    classname<T> $classname,
  ): ImmSet<HttpMethod> {
    $supported = Set { };
    $cf = $this->classFacts;
    if ($cf->doesImplement(SupportsGetRequests::class, $classname)) {
      $supported[] = HttpMethod::GET;
    }
    if ($cf->doesImplement(SupportsPostRequests::class, $classname)) {
      $supported[] = HttpMethod::POST;
    }

    invariant(
      !$supported->isEmpty(),
      '%s implements %s, but does not implement %s or %s',
      $classname,
      IncludeInUriMap::class,
      SupportsGetRequests::class,
      SupportsPostRequests::class,
    );

    /* This is me being opinionated, not a technical limitation:
     *
     * I think each controller should do one thing. Multiple HTTP methods
     * implies it does multiple things.
     *
     * Returning a set instead of a single method so it's easy to change
     * if someone convinces me that this is a bad idea.
     */
    invariant(
      $supported->count() === 1,
      '%s is marked as supporting multiple HTTP methods; build 1 controller '.
      'per method instead, refactoring common code out (eg to a trait).',
      $classname,
    );

    return $supported->immutable();
  }
}