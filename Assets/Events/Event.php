<?php
namespace Pam\Assets\Events;

use Pam\Utils\Date;

class Event implements \JsonSerializable
{
    const TYPE_BUILDING = 'building';

    const STATUS_ARCHIVED = 'ARCHIVED';
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_UPCOMING = 'UPCOMING';

    //Date format: 8 February 2017 8:05 AM
    const DATE_FORMAT = 'd F Y g:i A';

    const FEATURE_EVENT_TYPE_SOLID_BACKGROUND = 'solid-background';
    const FEATURE_EVENT_TYPE_IMAGE_BACKGROUND = 'image-background';
    const IMAGES_FOLDER = 'feature-events';

    private static $eventTypes = [
        self::TYPE_BUILDING => 'Building Specific',
    ];

    private static $notifyIntervals = [
        1 => '1 Hour',
        2 => '2 Hours',
        4 => '4 Hours',
        6 => '6 Hours',
        8 => '8 Hours',
    ];

    private static $featureEventTypes = [
        self::FEATURE_EVENT_TYPE_SOLID_BACKGROUND => 'Solid Background Colour',
        self::FEATURE_EVENT_TYPE_IMAGE_BACKGROUND => 'Background Image',
    ];


    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $type;

    /**
     * @var int
     */
    private $assetId;

    /**
     * @var int
     */
    private $dictionaryId;

    /**
     * @var int[]
     */
    private $locationIds = [];

    /**
     * Event start datetime in UTC time zone
     * @var \DateTime
     */
    private $startTime;

    /**
     * Event start datetime in UTC time zone
     * @var \DateTime
     */
    private $endTime;

    /**
     * @var \DateInterval
     */
    private $notifyInterval;

    /**
     * @var string
     */
    private $colourSchemeCode;

    /**
     * @var bool
     */
    private $featureEvent;

    /**
     * @var string
     */
    private $landscapeImageId;

    /**
     * @var string
     */
    private $portraitImageId;

    /**
     * @var string
     */
    private $imageTextColour;

    /**
     * @var array
     */
    private $destination = [];

    /**
     * @var array
     */
    private $screenAssets = [];

    /**
     * @param array $row
     * @return Event
     */
    public static function createByDbRow($row)
    {
        $event = new Event();
        $event->id = $row['id'];
        $event->name = $row['name'];
        $event->type = $row['type'];
        $event->startTime = Date::getUtcDateTimeObject($row['startTime']);
        $event->endTime = Date::getUtcDateTimeObject($row['endTime']);
        $event->notifyInterval = new \DateInterval("PT{$row['notifyInterval']}H");
        $event->assetId = $row['assetId'];
        $event->dictionaryId = $row['dictionaryId'];
        $event->featureEvent = (bool)$row['isFeatureEvent'];
        $event->colourSchemeCode = $row['colourSchemeCode'];
        $event->landscapeImageId = $row['landscapeImageId'];
        $event->portraitImageId = $row['portraitImageId'];
        $event->imageTextColour = $row['imageTextColourHex'];
        return $event;
    }

    /**
     * @return array
     */
    public function toDbRow()
    {
        $startTime = $this->getStartTime();
        $startTimeString = $startTime instanceof \DateTime ? $startTime->format(Date::DATE_TIME_FORMAT) : null;
        $endTime = $this->getEndTime();
        $endTimeString = $endTime instanceof \DateTime ? $endTime->format(Date::DATE_TIME_FORMAT) : null;
        $row = [
            'name' => $this->getName(),
            'type' => $this->getType(),
            'assetId' => $this->assetId,
            'dictionaryId' => $this->dictionaryId,
            'startTime' => $startTimeString,
            'endTime' => $endTimeString,
            'notifyInterval' => $this->getNotifyIntervalString(),
            'isFeatureEvent' => (int)$this->isFeatureEvent(),
            'colourSchemeCode' => $this->getColourSchemeCode(),
            'landscapeImageId' => $this->getLandscapeImageId(),
            'portraitImageId' => $this->getPortraitImageId(),
            'imageTextColourHex' => $this->getImageTextColour(),
        ];
        return $row;
    }

    /**
     * @return bool
     */
    public function isNew()
    {
        return is_null($this->getId());
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        if (array_key_exists($type, static::$eventTypes)) {
            $this->type = $type;
        }
    }

    /**
     * @return string
     */
    public function getDisplayType()
    {
        return array_key_exists($this->getType(), static::$eventTypes) ? static::$eventTypes[$this->getType()]: '';
    }

    /**
     * @return int
     */
    public function getDestinationAssetId()
    {
        return $this->assetId;
    }

    /**
     * @return int
     */
    public function getDestinationDictionaryId()
    {
        return $this->dictionaryId;
    }

    /**
     * @param int $assetId
     * @param int $dictionaryId
     */
    public function setDestinationIds($assetId, $dictionaryId)
    {
        $this->assetId = $assetId;
        $this->dictionaryId = $dictionaryId;
    }

    /**
     * @return string
     */
    public function getDisplayDestination()
    {
        $displayDestination = '';
        if (!empty($this->destination)) {
            $displayDestination = "{$this->destination['copy']} - {$this->destination['buildingCode']}.{$this->destination['levelCodeDisplay']}";
            if ($this->destination['roomNumber'] != '') {
                $displayDestination .= ".{$this->destination['roomNumber']}";
            }
        }
        return $displayDestination;
    }

    /**
     * @return array
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * @param array $destination
     */
    public function setDestination($destination)
    {
        $this->destination = $destination;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        $currentDateTime = Date::getUtcDateTimeObject();
        if ($currentDateTime < $this->startTime) {
            return static::STATUS_UPCOMING;
        } else if ($currentDateTime <= $this->endTime) {
            return static::STATUS_ACTIVE;
        } else {
            return static::STATUS_ARCHIVED;
        }
    }

    /**
     * @return \DateTime
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @return string
     */
    public function getDisplayStartTime()
    {
        return Date::getDisplayTime($this->getStartTime());
    }

    /**
     * @return string
     */
    public function getDisplayStartDate()
    {
        return Date::getDisplayDate($this->getStartTime());
    }

    /**
     * @param \DateTime $startTime
     */
    public function setStartTime(\DateTime $startTime)
    {
        $this->startTime = $startTime;
    }

    /**
     * @return \DateTime
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @return string
     */
    public function getDisplayEndTime()
    {
        return Date::getDisplayTime($this->getEndTime()
        );
    }

    /**
     * @return string
     */
    public function getDisplayEndDate()
    {
        return Date::getDisplayDate($this->getEndTime());
    }

    /**
     * @param \DateTime $endTime
     */
    public function setEndTime(\DateTime $endTime)
    {
        $this->endTime = $endTime;
    }

    /**
     * @return \DateInterval
     */
    public function getNotifyInterval()
    {
        return $this->notifyInterval;
    }

    /**
     * @return null|string
     */
    public function getNotifyIntervalString()
    {
        $notifyInterval = $this->getNotifyInterval();
        return $notifyInterval instanceof \DateInterval ? $notifyInterval->format('%h') : null;
    }

    /**
     * @param \DateInterval|string $notifyInterval
     */
    public function setNotifyInterval($notifyInterval)
    {
        if (!($notifyInterval instanceof \DateInterval)) {
            $notifyInterval = new \DateInterval("PT{$notifyInterval}H");
        }
        $this->notifyInterval = $notifyInterval;
    }

    /**
     * @return int[]
     */
    public function getLocationIds()
    {
        return $this->locationIds;
    }

    /**
     * @return int[]
     */
    public function getLocationBuildingCodes()
    {
        $buildingCodes = [];
        foreach ($this->getLocationIds() as $locationId) {
            if (isset($this->screenAssets[$locationId])) {
                $screenAsset = $this->screenAssets[$locationId];
                $buildingCodes[$screenAsset['building']] = $screenAsset['building'];
            }
        }
        return $buildingCodes;
    }

    /**
     * @return string
     */
    public function getDisplayLocations()
    {
        if (count($this->screenAssets) == count($this->getLocationIds())) {
            return 'All Screens';
        }
        $displayLocations = implode(', ', array_map(function($buildingCode) {
            return "Building {$buildingCode}";
        }, $this->getLocationBuildingCodes()));
        return $displayLocations;
    }

    /**
     * @param int[] $assetIds
     */
    public function setLocations($assetIds)
    {
        $this->locationIds = $assetIds;
    }

    /**
     * @param array $screenAssets
     */
    public function setScreenAssets($screenAssets)
    {
        $this->screenAssets = $screenAssets;
    }

    /**
     * @return string
     */
    public function getColourSchemeCode()
    {
        return $this->colourSchemeCode;
    }

    /**
     * @param string $colourSchemeCode
     */
    public function setColourSchemeCode($colourSchemeCode)
    {
        $this->colourSchemeCode = $colourSchemeCode;
    }

    /**
     * @return bool
     */
    public function isFeatureEvent()
    {
        return $this->featureEvent;
    }

    /**
     * @param bool $isFeatureEvent
     */
    public function setFeatureEvent($isFeatureEvent)
    {
        $this->featureEvent = $isFeatureEvent;
    }

    /**
     * @return string
     */
    public function getFeatureEventType()
    {
        return $this->landscapeImageId ? static::FEATURE_EVENT_TYPE_IMAGE_BACKGROUND : static::FEATURE_EVENT_TYPE_SOLID_BACKGROUND;
    }

    /**
     * @return string
     */
    public function getLandscapeImageId()
    {
        return $this->landscapeImageId;
    }

    /**
     * @param string $landscapeImageId
     */
    public function setLandscapeImageId($landscapeImageId)
    {
        $this->landscapeImageId = $landscapeImageId;
    }

    /**
     * @return string
     */
    public function getPortraitImageId()
    {
        return $this->portraitImageId;
    }

    /**
     * @param string $portraitImageId
     */
    public function setPortraitImageId($portraitImageId)
    {
        $this->portraitImageId = $portraitImageId;
    }

    /**
     * @return string
     */
    public function getImageTextColour()
    {
        return $this->imageTextColour;
    }

    /**
     * @param string $imageTextColour
     */
    public function setImageTextColour($imageTextColour)
    {
        $this->imageTextColour = $imageTextColour;
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
        $now = new \DateTime('now', new \DateTimeZone('utc'));
        $start = $this->getStartTime()->sub($this->getNotifyInterval());
        $end = $this->getEndTime();

        return $start <= $now && $now <= $end;
    }

    /**
     * @return array
     */
    public static function getAvailableEventTypes()
    {
        return static::$eventTypes;
    }

    /**
     * @return array
     */
    public static function getAvailableNotifyIntervals()
    {
        return static::$notifyIntervals;
    }

    /**
     * @return array
     */
    public static function getAvailableFeatureEventTypes()
    {
        return static::$featureEventTypes;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'displayType' => $this->getDisplayType(),
            'assetId' => $this->getDestinationAssetId(),
            'status' => $this->getStatus(),
            'displayDestination' => $this->getDisplayDestination(),
            'dictionaryId' => $this->getDestinationDictionaryId(),
            'startTime' => $this->getDisplayStartTime(),
            'startDate' => $this->getDisplayStartDate(),
            'endTime' => $this->getDisplayEndTime(),
            'endDate' =>  $this->getDisplayEndDate(),
            'notifyInterval' => $this->getNotifyIntervalString(),
            'locationIds' => $this->getLocationIds(),
            'locationBuildingCodes' => $this->getLocationBuildingCodes(),
            'displayLocations' => $this->getDisplayLocations(),
            'featureEvent' => $this->isFeatureEvent(),
            'colourSchemeCode' => $this->getColourSchemeCode(),
            'featureEventType' => $this->getFeatureEventType(),
            'landscapeImageId' => $this->getLandscapeImageId(),
            'portraitImageId' => $this->getPortraitImageId(),
            'imageTextColour' => $this->getImageTextColour(),
        ];
    }

    /**
     * @param array $item
     * @return bool
     */
    public function isBuildingMatched($item) {
        return
            $this->getDestinationAssetId() == $item['asset_id'] ||
            '#' . $this->getDestinationDictionaryId() == $item['id'];
    }
}
