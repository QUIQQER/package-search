<?php

namespace QUI\Search\Items;

use QUI;
use QUI\Interfaces\Projects\Site as QUISiteInterface;

/**
 * Class CustomSearchItem
 *
 * Special custom search item for fulltext and quicksearch
 *
 * This is handled like a real Site in the search context
 */
class CustomSearchItem extends QUI\QDOM implements QUISiteInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $origin;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var QUI\Projects\Project
     */
    protected $Project = null;

    /**
     * CustomFulltextItem constructor.
     *
     * @param int $id - Custom ID that can be freely chosen and that uniquely represents the search item; must be unique for your origin!
     * @param string $origin  - Package this instance originates from (e.g. "quiqqer/search")
     * @param string $title - Title that is shown as a search list result item title or quicksearch suggestion
     * @param string $url - The URL that leads to your search item Site
     * @param array $attributes (optional) - Site-like attributes (e.g. "image_site", "short" etc.)
     */
    public function __construct(
        int $id,
        string $origin,
        string $title,
        string $url,
        array $attributes = []
    ) {
        $this->id     = $id;
        $this->origin = $origin;
        $this->url    = $url;

        $this->setAttributes($attributes);
        $this->setAttribute('title', $title);
    }

    /**
     * @return string
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }

    /**
     * Return the project object of the site
     *
     * @return QUI\Projects\Project
     */
    public function getProject()
    {
        if (empty($this->Project)) {
            return QUI::getRewrite()->getProject();
        }

        return $this->Project;
    }

    /**
     * @param QUI\Projects\Project $Project
     */
    public function setProject(QUI\Projects\Project $Project): void
    {
        $this->Project = $Project;
    }

    /**
     * Lädt die Plugins der Seite
     *
     * @param string|boolean $plugin - Plugin welches geladen werden soll, optional, ansonsten werden alle geladen
     *
     * @return QUISiteInterface
     */
    public function load($plugin = false)
    {
        return $this;
    }

    /**
     * Serialisierungsdaten
     *
     * @return string
     */
    public function encode()
    {
        return '';
    }

    /**
     * Setzt JSON Parameter
     *
     * @param string $params - JSON encoded string
     *
     * @throws QUI\Exception
     */
    public function decode($params)
    {
        return;
    }

    /**
     * Hohlt frisch die Daten aus der DB
     */
    public function refresh()
    {
        return;
    }

    /**
     * Prüft ob es eine Verknüpfung ist
     *
     * @return boolean|integer
     */
    public function isLinked()
    {
        return false;
    }

    /**
     * Prüft ob es die Seite auch in einer anderen Sprache gibt
     *
     * @param string $lang
     * @param boolean $check_only_active - check only active pages
     *
     * @return boolean
     */
    public function existLang($lang, $check_only_active = true)
    {
        return false;
    }

    /**
     * Gibt die IDs von Sprachverknüpfungen zurück
     *
     * @return array
     */
    public function getLangIds()
    {
        return [];
    }

    /**
     * Return the ID of the site,
     * or the ID of the sibling (linked) site of another languager
     *
     * @param string|boolean $lang - optional, if it is set, then the language of the wanted linked sibling site
     *
     * @return integer
     */
    public function getId($lang = false)
    {
        return $this->id;
    }

    /**
     * Gibt alle Kinder zurück
     *
     * @param array $params - Parameter für die Childrenausgabe
     *                      $params['where']
     *                      $params['limit']
     * @param boolean $load - Legt fest ob die Kinder die Plugins laden sollen
     *
     * @return array;
     */
    public function getChildren($params = [], $load = false)
    {
        return [];
    }

    /**
     * Liefert die nächstfolgende Seite
     *
     * @return QUI\Projects\Site
     * @throws QUI\Exception
     */
    public function nextSibling()
    {
        throw new QUI\Exception(
            QUI::getLocale()->get('quiqqer/quiqqer', 'exception.site.no.next.sibling')
        );
    }

    /**
     * Die nächsten x Kinder
     *
     * @param integer $no
     *
     * @return array
     */
    public function nextSiblings($no)
    {
        return [];
    }

    /**
     * Liefert die vorhergehenden Seite
     *
     * @return QUI\Projects\Site
     * @throws QUI\Exception
     */
    public function previousSibling()
    {
        throw new QUI\Exception(
            QUI::getLocale()->get('quiqqer/quiqqer', 'exception.site.no.previous.sibling')
        );
    }

    /**
     * Die x vorhergehenden Geschwister
     *
     * @param integer $no
     *
     * @return array
     */
    public function previousSiblings($no)
    {
        return [];
    }

    /**
     * Gibt das erste Kind der Seite zurück
     *
     * @param array $params
     *
     * @return QUI\Projects\Site | false
     */
    public function firstChild($params = [])
    {
        return false;
    }

    /**
     * Gibt die Kinder zurück achtet aber auf "Nicht in Navigation anzeigen" und Rechte
     *
     * @param array $params
     *
     * @return array
     */
    public function getNavigation($params = [])
    {
        return [];
    }

    /**
     * Gibt ein Kind zurück welches den Namen hat
     *
     * @param string $name
     *
     * @return integer
     * @throws QUI\Exception
     */
    public function getChildIdByName($name)
    {
        throw new QUI\Exception(
            QUI::getLocale()->get('quiqqer/quiqqer', 'exception.site.child.by.name.not.found', [
                'name' => $name
            ]),
            705
        );
    }

    /**
     * Return a children by id
     *
     * @param integer $id
     *
     * @return QUI\Projects\Site
     * @throws QUI\Exception
     */
    public function getChild($id)
    {
        throw new QUI\Exception(
            QUI::getLocale()->get('quiqqer/quiqqer', 'exception.site.child.not.found'),
            705
        );
    }

    /**
     * Gibt die ID's der Kinder zurück
     * Wenn nur die ID's verwendet werden sollte dies vor getChildren verwendet werden
     *
     * @param array $params Parameter für die Childrenausgabe
     *                      $params['where']
     *                      $params['limit']
     *
     * @return array
     */
    public function getChildrenIds($params = [])
    {
        return [];
    }

    /**
     * Return ALL children ids under the site
     *
     * @param array $params - db parameter
     *
     * @return array
     */
    public function getChildrenIdsRecursive($params = [])
    {
        return [];
    }

    /**
     * Gibt zurück ob Site Kinder besitzt
     *
     * @param boolean $navhide - if navhide == false, navhide must be 0
     *
     * @return integer - Anzahl der Kinder
     */
    public function hasChildren($navhide = false)
    {
        return 0;
    }

    /**
     * Setzt das delete Flag
     *
     * @todo move to Site/Edit
     */
    public function delete()
    {
        return;
    }

    /**
     * Gibt die URL der Seite zurück
     *
     * @param $params
     * @param $rewrited
     *
     * @return string
     */
    public function getUrl($params = [], $rewrited = false)
    {
        return $this->url;
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function getUrlRewritten($params = [])
    {
        return $this->url;
    }

    /**
     * Return the Parent id from the site object
     *
     * @return integer
     */
    public function getParentId()
    {
        return 1;
    }

    /**
     * Gibt alle direkten Eltern Ids zurück
     *
     * Site
     * ->Parent
     * ->Parent
     * ->Parent
     *
     * @return array
     */
    public function getParentIds()
    {
        return [1];
    }

    /**
     * Return the Parent ID List
     *
     * @return array
     */
    public function getParentIdTree()
    {
        return [1];
    }

    /**
     * Gibt das Parent Objekt zurück.
     * Wenn kein Parent Objekt existiert wird false zurückgegeben.
     *
     * @return QUISiteInterface|false
     */
    public function getParent()
    {
        return $this->getProject()->get(1);
    }

    /**
     * Gibt alle rekursive Parents als Objekte zurück
     * Site->Parent->ParentParent->ParentParentParent
     *
     * @return array
     */
    public function getParents()
    {
        return [$this->getParent()];
    }

    /**
     * Stellt die Seite wieder her
     *
     * ??? wieso hier? und nicht im trash? O.o
     */
    public function restore()
    {
        return;
    }

    /**
     * Zerstört die Seite
     * Die Seite wird komplett aus der DB gelöscht und auch alle Beziehungen
     * Funktioniert nur wenn die Seite gelöscht ist
     */
    public function destroy()
    {
        return;
    }

    /**
     * Canonical URL - Um doppelte Inhalt zu vermeiden
     *
     * @return string
     */
    public function getCanonical()
    {
        return $this->url; // @todo
    }

    /**
     * Löscht den Seitencache
     */
    public function deleteCache()
    {
        return;
    }

    /**
     * Löscht den Seitencache
     */
    public function createCache()
    {
        return;
    }

    /**
     * Shortcut for QUI\Permissions\Permission::hasSitePermission
     *
     * @param string $permission - name of the permission
     * @param QUI\Users\User|boolean $User - optional
     *
     * @return boolean|integer
     */
    public function hasPermission($permission, $User = false)
    {
        return true;
    }

    /**
     * Shortcut for QUI\Permissions\Permission::checkSitePermission
     *
     * @param string $permission - name of the permission
     * @param QUI\Users\User|boolean $User - optional
     *
     * @throws QUI\Exception
     */
    public function checkPermission($permission, $User = false)
    {
        return;
    }

    /**
     * CustomFulltextItem data as array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'id'         => $this->id,
            'origin'     => $this->origin,
            'title'      => $this->getAttribute('title'),
            'url'        => $this->url,
            'attributes' => $this->getAttributes()
        ];
    }
}
