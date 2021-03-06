<?php

namespace RadioSolution\ProgramBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * RadioSolution\ProgramBundle\Entity\Program
 */
class Program
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var datetime $time_start
     */
    private $time_start;

    /**
     * @var datetime $time_stop
     */
    private $time_stop;

    /**
     * @var RadioSolution\PodcastBundle\Entity\Podcast
     */
    private $podcast;

    /**
     * @var RadioSolution\ProgramBundle\Entity\Emission
     */
    private $emission;

    private $collision;

    /**
     * @var \DateTime
     */
    private $created_at;

    /**
     * @var \DateTime
     */
    private $updated_at;

    public function __construct()
    {
    	$this->time_start = new \DateTime('now');
    	$this->time_stop = new \DateTime('now');

        if (empty($this->created_at)) {
            $this->created_at = new \Datetime();
        }
    }

    /**
     * set datetimes on create/update.
     */
    public function updatedTimestamps()
    {
        $this->setUpdatedAt(new \DateTime('now'));
        if ($this->getCreatedAt() == null) {
            $this->setCreatedAt(new \DateTime('now'));
        }
    }

    /**
     * to string
     *
     * @return string
     */
    public function __toString()
    {
        $str = (String) $this->getId();
        $emission = $this->getEmission();
        if ($emission && !empty($emission->getName())) {
            $str = $emission->getName();
        }
        $str .= ' - ' . $this->getTimeStart()->format('d/m/Y H:i') . ' - ' . $this->getTimeStop()->format('d/m/Y H:i');
    	return $str;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set time_start
     *
     * @param datetime $timeStart
     */
    public function setTimeStart($timeStart)
    {
        $this->time_start = $timeStart;
    }

    /**
     * Get time_start
     *
     * @return datetime
     */
    public function getTimeStart()
    {
        return $this->time_start;
    }

    /**
     * Set time_stop
     *
     * @param datetime $timeStop
     */
    public function setTimeStop($timeStop)
    {
        $this->time_stop = $timeStop;
    }

    /**
     * Get time_stop
     *
     * @return datetime
     */
    public function getTimeStop()
    {
        return $this->time_stop;
    }

    /**
     * Set podcast
     *
     * @param RadioSolution\PodcastBundle\Entity\Podcast $podcast
     */
    public function setPodcast(\RadioSolution\PodcastBundle\Entity\Podcast $podcast=NULL)
    {
        $this->podcast = $podcast;
    }

    public function removePodcast()
    {
        $this->podcast = null;
    }

    /**
     * Get podcast
     *
     * @return RadioSolution\PodcastBundle\Entity\Podcast
     */
    public function getPodcast()
    {
        return $this->podcast;
    }

    /**
     * Set emission
     *
     * @param RadioSolution\ProgramBundle\Entity\Emission $emission
     */
    public function setEmission(\RadioSolution\ProgramBundle\Entity\Emission $emission)
    {
        $this->emission = $emission;
        return $this;
    }

    /**
     * Get emission
     *
     * @return RadioSolution\ProgramBundle\Entity\Emission
     */
    public function getEmission()
    {
        return $this->emission;
    }

    public function getNameEmission()
    {
    	return  $this->emission->getName();
    }

    public function getDescriptionEmission()
    {
    	return  $this->emission->getDescription();
    }

    public function getSlug()
    {
    	return $this->emission->getSlug();
    }

    public function getImageEmission()
    {
    	return $this->emission->getMedia();
    }

    public function getCollision()
    {
    	return $this->collision;
    }

    public function setCollision($collision)
    {
    	$this->collision=$collision;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;
        return $this;
    }
}
