<?php

namespace flusio\utils\LinksTimeline;

use flusio\models;

/**
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class ViaGroup
{
    /** @var models\Collection|models\User */
    public mixed $via_resource;

    /** @var models\Link[] */
    public array $links = [];

    /**
     * @param models\Collection|models\User $via_resource
     **/
    public function __construct(mixed $via_resource)
    {
        $this->via_resource = $via_resource;
    }

    public function title(): string
    {
        if ($this->via_resource instanceof models\Collection) {
            return $this->via_resource->name();
        } else {
            return $this->via_resource->username;
        }
    }

    public function addLink(models\Link $link): void
    {
        $this->links[] = $link;
    }
}
