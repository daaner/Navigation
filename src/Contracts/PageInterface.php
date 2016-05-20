<?php

namespace KodiComponents\Navigation\Contracts;

use Illuminate\Support\Collection;

interface PageInterface extends NavigationInterface
{

    /**
     * @return string
     */
    public function getId();

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return string
     */
    public function getIcon();

    /**
     * @return string
     */
    public function getUrl();

    /**
     * @return int
     */
    public function getPriority();

    /**
     * @return bool
     */
    public function isActive();

    /**
     * @return $this
     */
    public function setActive();

    /**
     * @return PageInterface
     */
    public function getParent();

    /**
     * @return array
     */
    public function getPath();

    /**
     * @return array
     */
    public function getPathArray();
    
    /**
     * @return \Closure
     */
    public function getAccessLogic();

    /**
     * @return bool
     */
    public function checkAccess();
}
