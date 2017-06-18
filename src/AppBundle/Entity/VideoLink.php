<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: seyfer
 * Date: 6/18/17
 */

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class VideoLink
 * @package AppBundle\Entity
 *
 * @ORM\Entity(repositoryClass="VideoLinkRepository")
 * @ORM\Table(name="video_links")
 */
class VideoLink
{
    /**
     * @var integer
     * @ORM\Id()
     * @ORM\Column(type="integer", name="id")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(name="course_name", type="string")
     */
    protected $courseName;

    /**
     * @var string
     * @ORM\Column(name="video_name", type="string")
     */
    protected $videoName;

    /**
     * @var string
     * @ORM\Column(name="link", type="string")
     */
    protected $link;

    /**
     * @var boolean
     * @ORM\Column(name="downloaded", type="boolean")
     */
    protected $downloaded = false;

    /**
     * @var integer
     * @ORM\Column(name="position", type="integer", options={"default":"0"})
     */
    protected $position;

    /**
     * @var \DateTime
     * @ORM\Column(name="duration", type="datetime", nullable=true)
     */
    protected $duration;

    /**
     * VideoLink constructor.
     * @param string $courseName
     * @param string $videoName
     * @param string $link
     * @param $position
     * @param $duration
     */
    public function __construct(
        $courseName, $videoName, $link, $position, $duration
    )
    {
        $this->courseName = $courseName;
        $this->videoName  = $videoName;
        $this->link       = $link;
        $this->position   = $position;
        $this->duration   = $duration;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCourseName(): string
    {
        return $this->courseName;
    }

    /**
     * @param string $courseName
     */
    public function setCourseName(string $courseName)
    {
        $this->courseName = $courseName;
    }

    /**
     * @return string
     */
    public function getVideoName(): string
    {
        return $this->videoName;
    }

    /**
     * @param string $videoName
     */
    public function setVideoName(string $videoName)
    {
        $this->videoName = $videoName;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * @param string $link
     */
    public function setLink(string $link)
    {
        $this->link = $link;
    }

    /**
     * @return bool
     */
    public function isDownloaded(): bool
    {
        return $this->downloaded;
    }

    /**
     * @param bool $downloaded
     */
    public function setDownloaded(bool $downloaded)
    {
        $this->downloaded = $downloaded;
    }

    /**
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @param int $position
     */
    public function setPosition(int $position)
    {
        $this->position = $position;
    }

    /**
     * @return \DateTime
     */
    public function getDuration(): \DateTime
    {
        return $this->duration;
    }

    /**
     * @param \DateTime $duration
     */
    public function setDuration(\DateTime $duration)
    {
        $this->duration = $duration;
    }
}