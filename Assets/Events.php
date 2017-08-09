<?php
namespace Pam\Assets;

use Pam\Assets\Events\Event;
use Pam\Aws\S3;
use Pam\Db\Utils;
use Pam\Model;

class Events extends Model
{
    protected $tableName = 'event';
    protected $idColumn = 'id';

    /**
     * @return Event[]
     */
    public function fetchAll()
    {
        return $this->fetch();
    }

    /**
     * @param $id
     * @return Event|null
     */
    public function fetchOne($id)
    {
        $result = $this->fetch($id);
        if (!empty($result)) {
            return end($result);
        }
        return null;
    }

    /**
     * @param int|null $id
     * @return array
     */
    private function fetch($id = null)
    {
        $assignedLocations = $this->getAllAssignedLocations();
        $destinations = (new Destinations())->fetchDestinations();
        $digitalDirectory = new DigitalDirectory();
        $digitalScreens = $digitalDirectory->getAll(['building', 'level', 'location']);

        $rows = $id ? [$this->getOne($id)] : $this->getAll();
        $array = [];
        foreach ($rows as $row) {
            $event = Event::createByDbRow($row);
            if (isset($assignedLocations[$event->getId()])) {
                $event->setLocations($assignedLocations[$event->getId()]);
            }
            $event->setScreenAssets($digitalScreens);
            $destinationKey = $event->getDestinationAssetId() . '-' . $event->getDestinationDictionaryId();
            if (isset($destinations[$destinationKey])) {
                $event->setDestination($destinations[$destinationKey]);
            }
            $array[$event->getId()] = $event;
        }
        return $array;
    }

    /**
     * @param Event $event
     */
    public function save(Event $event)
    {
        if ($event->isNew()) {
            $id = $this->insert($event->toDbRow());
            $event->setId($id);
        } else {
            $this->update($event->getId(), $event->toDbRow());
        }
        $this->assignLocations($event);
    }

    /**
     * @param int $id
     */
    public function delete($id)
    {
        $eventRow = $this->getOne($id);
        if ($eventRow) {
            $event = Event::createByDbRow($eventRow);
            if ($event->getLandscapeImageId()) {
                $this->removeFeatureEventImageFromS3($event->getLandscapeImageId());
            }
            if ($event->getPortraitImageId()) {
                $this->removeFeatureEventImageFromS3($event->getPortraitImageId());
            }
            $id = mysqli_real_escape_string($this->db, $id);
            mysqli_query($this->db, "DELETE FROM `event_asset` WHERE eventId = {$id}") or die(mysqli_error($this->db));
            parent::delete($id);
        }
    }

    /**
     * @return array
     */
    private function getAllAssignedLocations()
    {
        $result = mysqli_query($this->db,"SELECT * FROM `event_asset`");
        $array = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $eventId = $row['eventId'];
            if (!isset($array[$eventId])) {
                $array[$eventId] = [];
            }
            $array[$eventId][] = $row['assetId'];
        };
        return $array;
    }

    /**
     * @param Event $event
     */
    private function assignLocations(Event $event)
    {
        $this->unassignLocation($event->getId());
        foreach ($event->getLocationIds() as $locationId) {
            $this->assignLocation($event->getId(), $locationId);
        }
    }

    /**
     * @param int $eventId
     * @param int $assetId
     */
    private function assignLocation($eventId, $assetId) {
        $eventId = mysqli_real_escape_string($this->db, $eventId);
        $assetId = mysqli_real_escape_string($this->db, $assetId);
        $insertQuery = "INSERT INTO `event_asset` (`eventId`, `assetId`) VALUES ('{$eventId}', '{$assetId}')";
        mysqli_query($this->db, $insertQuery) or die(mysqli_error($this->db));
    }

    /**
     * @param int $eventId
     */
    private function unassignLocation($eventId) {
        $eventId = mysqli_real_escape_string($this->db, $eventId);
        $query = "DELETE from `event_asset` WHERE `eventId` = $eventId";
        mysqli_query($this->db, $query) or die(mysqli_error($this->db));
    }

    /**
     * Uploads feature event image to S3 bucket into '/{clientId}/feature-events' folder
     * @param string $srcPath temp file upload path
     * @param string $originalFileName source file name
     * @param string $imageId destination file name, if empty - new GUID is generated and used as file name
     * @return null|string uploaded file ID in GUID format
     */
    public function uploadFeatureEventImageToS3($srcPath, $originalFileName, $imageId = null)
    {
        $tmp = explode('.', basename($originalFileName));
        $ext = strtolower(end($tmp));
        $customFileName = empty($imageId) ? generateGuid() : $imageId;
        return S3::instance()->save(file_get_contents($srcPath), $ext, $customFileName, Event::IMAGES_FOLDER);
    }

    /**
     * Removes feature event image from S3 bucket by '/{clientId}/feature-events/{imageId}' path
     * @param string $imageId
     * @return bool
     */
    public function removeFeatureEventImageFromS3($imageId) {
        return S3::instance()->delete($imageId, Event::IMAGES_FOLDER);
    }

    public function fetchIdsForUser($userId)
    {
        $userId = $this->db->real_escape_string($userId);
        $q = "SELECT eventId AS id FROM event_asset ea JOIN digital_directory_asset dda ON ea.assetId = dda.assetId WHERE dda.userId = '$userId'";
        $rows = $this->fetchRows($q);
        return array_column($rows, 'id');
    }
}