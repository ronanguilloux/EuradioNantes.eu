<?php
/**
 * This file is part of the EuradioNantes.eu package.
 *
 * (c) 2015 Les Polypodes
 * Made in Nantes, France - http://lespolypodes.com
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 *
 * File created by ronan@lespolypodes.com (13/08/2015 - 15:41)
 */

namespace RadioSolution\ProgramBundle\Service\Tracks;

use Monolog\Logger;
use RadioSolution\ProgramBundle\Entity\Broadcast;
use RadioSolution\ProgramBundle\Exception\InvalidTermsInputException;
use RadioSolution\ProgramBundle\Exception\InvalidTrackInputException;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\EntityManagerInterface;
use RadioSolution\ProgramBundle\Entity\Track;
use RadioSolution\ProgramBundle\Entity\Album as AlbumModel;
use RadioSolution\ProgramBundle\Exception\InvalidAlbumInputException;

/**
 * Class NowPlaying.
 */
class NowPlaying implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface|null
     */
    protected $container = null;

    /**
     * @var string 'Now Playing' FTP TXT file
     *
     * @example http://www.euradionantes.eu/uploads/onair/now_playing.txt
     */
    protected $nowPlayingUrl = null;

    /**
     * @var Logger
     */
    protected $logger = null;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager = null;

    /**
     * @var TrackRetriever
     */
    protected $retriever = null;

    /**
     * @var string
     */
    protected $terms = null;

    /**
     *
     */
    const ALREADY_EXISTS = 0;
    /**
     *
     */
    const NEWLY_SAVED = 1;
    /**
     *
     */
    const SOMETHING_TERRIBLE_HAPPENED = -1;

    /**
     * @param ContainerInterface $container
     * @param string             $nowPlayingUrl
     */
    public function __construct(ContainerInterface $container, $nowPlayingUrl)
    {
        $this->setContainer($container);
        $this->nowPlayingUrl = $nowPlayingUrl;
    }

    /**
     *
     */
    protected function setUp()
    {
        $this->retriever = $this->container->get('radiosolution.program.trackRetriever');
        $this->logger = $this->container->get('logger');
        $this->entityManager = $this->container->get('doctrine')->getManager();
    }

    /**
     * @param ContainerInterface|NULL $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        if (empty($container)) {
            throw new \InvalidArgumentException(sprintf("container argument passed to %s cannot be null", __METHOD__));
        }

        $this->container = $container;
    }

    /**
     * @return string
     */
    public function getTerms()
    {
        return $this->terms;
    }

    /**
     * @return $this
     */
    public function fetchTerms()
    {
        $this->terms = file_get_contents($this->nowPlayingUrl);
        $unProcessable = array(
            "EuradioNantes - La diversite europeenne au creux de l'oreille"
        );

        if(in_array(trim($this->terms), $unProcessable)) {
            $this->terms = null;
        }

        return $this;
    }


    /**
     * @return array [$currentTrack, $broadcast, $this->terms, $albumModel, $trackList]
     * @throws InvalidAlbumInputException
     * @throws InvalidTermsInputException
     * @throws InvalidTrackInputException
     */
    public function execute(string $search = null)
    {
        if (!empty($search)) {
            $this->terms = $search;
        }
        if (!isset($this->retriever)) {
            $this->setUp();
        }
        if (!isset($this->terms)) {
            $this->fetchTerms();
        }

        if(isset($this->terms)) {
            list($currentTrackTitle, $terms, $album, $images, $tracks) = $this->retriever->search($this->terms);
            list($code, $albumModel) = $this->saveAlbum($album, $images, $terms);
            switch($code) {
                case self::NEWLY_SAVED:
                    if ($tracks) {
                        $trackList = $this->saveTrackList($albumModel, $terms, $tracks);
                    } else {
                        $trackList = $albumModel->getTracks();
                    }
                    break;
                case self::ALREADY_EXISTS:
                    $trackList = $albumModel->getTracks();
                    break;
                default:
                    throw new InvalidTermsInputException(sprintf("album couldn't be saved in %s", __METHOD__));
            }
            $currentTrack = $this->getTrackByTitle($trackList, $currentTrackTitle);
            list($code, $broadcast) = $this->saveBroadcast($currentTrack);

            if(empty($broadcast) || $code == self::SOMETHING_TERRIBLE_HAPPENED) {
                throw new InvalidTrackInputException(sprintf("broadcast couldn't be saved using given track in %s", __METHOD__));
            }

            return array($currentTrack, $broadcast, $this->terms, $albumModel, $trackList);
        }

        return array();
    }


    /**
     * @param $album
     * @param $images
     * @param $terms
     *
     * @return AlbumModel
     * @throws InvalidAlbumInputException
     */
    protected function saveAlbum($album, $images, $terms)
    {
        $albumModel = new AlbumModel();
        if(empty($album) || empty($images) || empty($terms)) {
            var_dump($album, $images, $terms);
            throw new InvalidAlbumInputException(sprintf("album, images nor terms cannot be null in %s", __METHOD__));
        }
        try {
            $this->logger->info(sprintf('Trying to SAVE %s ALBUM INFOS using the TrackRetriever', $terms));
            $albumModel->fromXml($album);
            $albumModel->setImagesfromXml($images);
            $albumModel->setPublished(true);

            $exists = $this->albumExistsYet($albumModel);
            if('RadioSolution\ProgramBundle\Entity\Album' === get_class($exists)) {
                $this->logger->info(sprintf(
                    'Album #%d "%s" already existing found for terms "%s"',
                    $exists->getId(), $exists->getTitle(), $terms
                ));

                return array(self::ALREADY_EXISTS, $exists);
            }
            $this->entityManager->persist($albumModel);
            $this->entityManager->flush();
        } catch (InvalidAlbumInputException $e) {
            $this->logger->error(sprintf('An error occurred : %s', $e));
            return array(self::SOMETHING_TERRIBLE_HAPPENED, null);
        }

        return array(self::NEWLY_SAVED, $albumModel);
    }

    /**
     * @param AlbumModel $album
     *
     * @return AlbumModel
     */
    public function albumExistsYet(AlbumModel $album)
    {
        $result = null;

        if (!isset($this->entityManager)) {
            $this->setUp();
        }

        return $this->entityManager->getRepository("ProgramBundle:Album")->findOneBy(
            array(
                'title' =>  $album->getTitle(),
                'artist' => $album->getArtist()
            )
        );
    }

    /**
     * @param Track $track
     *
     * @return array
     * @throws InvalidTrackInputException
     */
    protected function saveBroadCast($track)
    {
        $broadcast = new Broadcast();
        if(empty($track)) {
            throw new InvalidTrackInputException(sprintf("track entity cannot be null in %s", __METHOD__));
        }
        try {
            $this->logger->info(sprintf('Trying to SAVE broadcast using Track %d "%s"',
                $track->getId(), $track->getTitle()));

            $broadcast->setBroadcasted(new \DateTime('now'));
            $broadcast->setTrack($track);
            $this->entityManager->persist($broadcast);
            $this->entityManager->flush();

        } catch (InvalidAlbumInputException $e) {
            $this->logger->error(sprintf('An error occurred : %s', $e));
            return array(self::SOMETHING_TERRIBLE_HAPPENED, null);
        }

        return array(self::NEWLY_SAVED, $broadcast);
    }

    /**
     * @param Track[] $trackList
     * @param string $currentTrackTitle
     *
     * @return null
     * @throws InvalidTrackInputException
     */
    protected function getTrackByTitle($trackList = array(), $currentTrackTitle = '')
    {
        $found = null;
        if(empty($trackList) || empty($currentTrackTitle)) {
            throw new InvalidTrackInputException(sprintf('trackList or currentTrackTitle are empty', __METHOD__));
        }

        foreach($trackList as $track) {
            if(trim(strtolower($currentTrackTitle)) == trim(strtolower($track->getTitle()))) {
                $found = $track;
                break;
            }
        }

        return $found;
    }

    /**
     * @param AlbumModel $albumModel
     * @param string     $terms
     * @param array      $tracks
     *
     * @return array
     */
    protected function saveTrackList(AlbumModel $albumModel, $terms = '', $tracks = array())
    {
        $trackList = array();
        if ($albumModel->getId()) {
            foreach ($tracks as $position => $title) {
                if ("titre inconnu" == trim(strtolower($title))) {
                    $this->logger->info(
                        sprintf('INGORING %s - %s TRACK INFOS while processsing %s (album #%s) track list',
                            $terms, $title, $albumModel->getId()));
                    continue;
                }
                try {
                    $this->logger->info(sprintf('Trying to SAVE %s - %s TRACK INFOS using the TrackRetriever', $terms, $title));
                    $trackModel = new Track();
                    $trackModel->fromAlbum($albumModel, $title, $position+1); // position is an index's an index
                    $this->entityManager->persist($trackModel);
                    $this->entityManager->flush();
                    $trackList[] = $trackModel;
                } catch (InvalidAlbumInputException $e) {
                    $this->logger->error(sprintf('An error occurred : %s', $e));
                }
            }
        }

        return $trackList;
    }
}