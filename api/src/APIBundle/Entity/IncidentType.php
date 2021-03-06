<?php

namespace APIBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="incident_types")
 */
class IncidentType
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $type_id;

    /**
     * @ORM\Column(type="string")
     */
    protected $type_name;

    public function getTypeId()
    {
        return $this->type_id;
    }

    public function setTypeId($id)
    {
        $this->type_id = $id;
        return $this;
    }

    public function getTypeName()
    {
        return $this->type_name;
    }

    public function setTypeName($name)
    {
        $this->type_name = $name;
        return $this;
    }
}