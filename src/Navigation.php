<?php

namespace KodiComponents\Navigation;

use Closure;
use KodiComponents\Navigation\Contracts\NavigationInterface;
use KodiComponents\Navigation\Contracts\PageInterface;

class Navigation implements NavigationInterface
{

    /**
     * @param array $data
     * @param string $class
     *
     * @return PageInterface
     */
    public static function makePage(array $data, $class = PageInterface::class)
    {
        $page = app($class);

        foreach ($data as $key => $value) {
            if ($key != 'pages' and method_exists($page, $method = 'set'.ucfirst($key))) {
                $page->{$method}($value);
            }
        }

        if (isset($data['pages']) and is_array($data['pages'])) {
            foreach ($data['pages'] as $child) {
                $page->addPage($child);
            }
        }

        return $page;
    }

    /**
     * @var PageCollection
     */
    protected $items;

    /**
     * @var Closure
     */
    protected $accessLogic;

    /**
     * @var null|string
     */
    private $currentUrl;

    /**
     * @var PageInterface|null
     */
    private $current;

    /**
     * Navigation constructor.
     *
     * @param array|null $pages
     */
    public function __construct(array $pages = null)
    {
        $this->items = new PageCollection();

        if (! is_null($pages)) {
            $this->setFromArray($pages);
        }
    }

    /**
     * @return null|string
     */
    public function getCurrentUrl()
    {
        if (is_null($this->currentUrl)) {
            return url()->current();
        }

        return $this->currentUrl;
    }

    /**
     * @param null|string $url
     *
     * @return $this
     */
    public function setCurrentUrl($url)
    {
        $this->currentUrl = $url;

        return $this;
    }

    /**
     * @param array $navigation
     */
    public function setFromArray(array $navigation)
    {
        foreach ($navigation as $page) {
            $this->addPage($page);
        }
    }

    /**
     * @param string|array|PageInterface $page
     *
     * @return PageInterface
     */
    public function addPage($page)
    {
        if (is_array($page)) {
            $page = static::makePage($page);
        } elseif (is_string($page) or is_null($page)) {
            $page = app(PageInterface::class, [$page]);
        }

        if (! ($page instanceof PageInterface)) {
            return;
        }

        $this->getPages()->push($page);

        return $page;
    }

    /**
     * @return PageCollection
     */
    public function getPages()
    {
        return $this->items;
    }

    /**
     * @return int
     */
    public function countPages()
    {
        $count = 0;

        $this->getPages()->each(function (PageInterface $page) use (&$count) {
            $count++;
            $count += $page->countPages();
        });

        return $count;
    }

    /**
     * @param Closure $callback
     *
     * @return $this
     */
    public function setPages(Closure $callback)
    {
        call_user_func($callback, $this);

        return $this;
    }

    /**
     * @param Closure $accessLogic
     *
     * @return $this
     */
    public function setAccessLogic(Closure $accessLogic)
    {
        $this->accessLogic = $accessLogic;

        return $this;
    }

    /**
     * @return Closure
     */
    public function getAccessLogic()
    {
        return is_callable($this->accessLogic)
            ? $this->accessLogic
            : true;
    }

    /**
     * @return bool
     */
    public function hasChild()
    {
        return $this->getPages()->count() > 0;
    }

    /**
     * @return PageInterface|null
     */
    public function getCurrent()
    {
        $this->findActivePage();

        return $this->current;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->getPages()->toArray();
    }

    /**
     * @param string|null $view
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function render($view = null)
    {
        $this->findActivePage();
        $this->filterByAccessRights();
        $this->sort();

        if (is_null($view)) {
            $view = config('navigation.view.navigation', 'navigation::navigation');
        }

        return view($view, [
            'pages' => $this->getPages(),
        ])->render();
    }

    /**
     * @return $this
     */
    public function filterByAccessRights()
    {
        $this->items = $this->getPages()->filterByAccessRights();

        return $this;
    }

    /**
     * @return $this
     */
    public function sort()
    {
        $this->items = $this->getPages()->sortByPriority();

        return $this;
    }

    /**
     * @return bool
     */
    protected function findActivePage()
    {
        if (! is_null($this->current)) {
            return true;
        }

        $foundPages = [];

        $url = $this->getCurrentUrl();

        $this->getPages()->each(function (PageInterface $page) use ($url, & $foundPages) {
            if (strpos($url, $page->getUrl()) !== false) {
                $foundPages[] = [
                    levenshtein($url, $page->getUrl()),
                    $page,
                ];
            }

            $page->findActive($url, $foundPages);
        });

        $calculates = [];

        foreach ($foundPages as $data) {
            $calculates[] = $data[0];
        }

        if (count($calculates)) {
            $this->current = array_get($foundPages, array_search(min($calculates), $calculates).'.1');
        }

        if (! is_null($this->current)) {
            $this->current->setActive();
        }

        return false;
    }

    /**
     * @param string $url
     * @param PageInterface[] $foundPages
     *
     */
    protected function findActive($url, array & $foundPages)
    {
        $this->getPages()->each(function (PageInterface $page) use ($url, &$foundPages) {
            if (strpos($url, $page->getUrl()) !== false) {
                $foundPages[] = [
                    levenshtein($url, $page->getUrl()),
                    $page,
                ];
            }

            $page->findActive($url, $foundPages);
        });
    }
}
