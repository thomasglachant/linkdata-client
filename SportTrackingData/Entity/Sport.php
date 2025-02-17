<?php

declare(strict_types=1);

namespace SportTrackingDataSdk\SportTrackingData\Entity;

use SportTrackingDataSdk\ClientHydra\Annotation\Cache;
use SportTrackingDataSdk\ClientHydra\Proxy\ProxyObject;
use SportTrackingDataSdk\ClientHydra\Utils\TranslatedPropertiesTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @Cache(
 *     public=true,
 *     ttl=3600,
 *     warmup=true,
 * )
 *
 * @method int       getId()
 * @method void      setId(int $id)
 * @method array     getTranslatedNames()
 * @method void      setTranslatedNames(array $translatedNames)
 * @method Universe  getUniverse()
 * @method void      setUniverse(Universe $universe)
 * @method bool      isActive()
 * @method void      setActive(bool $active)
 * @method \DateTime getCreatedAt()
 * @method void      setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void      setUpdatedAt(\DateTime $updatedAt)
 */
class Sport extends ProxyObject
{
    use TranslatedPropertiesTrait;

    public const ALPINISME = 153;
    public const AQUAGYM = 260;
    public const ARTS_MARTIAUX = 45;
    public const AVIRON = 263;
    public const BADMINTON = 335;
    public const BASKETBALL = 10;
    public const BMX = 360;
    public const BODYBOARD = 264;
    public const BOXE = 35;
    public const CANOE_KAYAK = 265;
    public const COURSE_A_PIED = 121;
    public const DANSE = 79;
    public const ESCALADE = 161;
    public const FITNESS = 91;
    public const FOOTBALL = 13;
    public const GOLF = 320;
    public const HANDBALL = 18;
    public const HOCKEY = 20;
    public const HOCKEY_SUR_GLACE = 21;
    public const KITESURF = 273;
    public const MARCHE = 113;
    public const MUSCULATION_EN_SALLE = 98;
    public const NATATION = 274;
    public const PLANCHE_A_VOILE = 280;
    public const PLONGEE = 284;
    public const RAMEUR = 398;
    public const RANDONNEE = 168;
    public const RANDONNEE_EQUESTRE = 200;
    public const RAQUETTE_A_NEIGE = 173;
    public const ROLLER = 367;
    public const RUGBY = 27;
    public const SKATEBOARD = 374;
    public const SKI = 174;
    public const SKI_ALPIN = 176;
    public const SNOWBOARD = 185;
    public const SURF = 296;
    public const TAPIS_DE_COURSE = 395;
    public const TENNIS = 357;
    public const TRAIL = 126;
    public const TRIATHLON = 77;
    public const TROTINETTE = 380;
    public const VELO_ELLIPTIQUE = 397;
    public const VELO_EN_SALLE = 110;
    public const VELO_SUR_ROUTE = 385;
    public const VOILE = 301;
    public const VOLLEYBALL = 32;
    public const VTT = 388;
    public const COURSE_D_ORIENTATION = 127;
    public const MARCHE_NORDIQUE = 114;
    public const RUN_AND_BIKE = 399;
    public const YOGA = 105;
    public const SKI_DE_RANDONNEE = 177;
    public const PARAPENTE = 7;
    public const TIR_A_L_ARC = 326;
    public const STAND_UP_PADDLE = 400;
    public const PADEL = 340;
    public const SQUASH = 354;
    public const TENNIS_DE_TABLE = 358;
    public const HOME_TRAINER = 401;
    public const SKI_DE_FOND_SKATING = 184;
    public const SKI_DE_FOND_ALTERNATIF = 183;
    public const CHAR_A_VOILE = 366;
    public const ACTIVITY_QUOTIDIENNE = 402;
    public const PILATES = 109;
    public const ELLIPTICAL_TRAINER = 397;
    public const DAILY_ACTIVITY = 402;
    public const COURSE_A_PIED_SUR_PISTE = 122;
    public const COURSE_A_PIED_SUR_ROUTE = 123;
    public const COURSE_A_PIED_EN_NATURE = 124;
    public const CROSS = 125;
    public const CROSS_TRAINING = 404;
    public const DIX_KM = 132;
    public const SEMI_MARATHON = 133;
    public const MARATHON = 134;
    public const CENT_KM = 135;
    public const TRAIL_COURT = 136;
    public const TRAIL_LONG = 137;
    public const ULTRA_TRAIL = 138;
    public const BMX_RACE = 361;
    public const BMX_STREET = 362;
    public const BMX_TRIAL = 363;
    public const PLAYBIKE = 364;
    public const VELO = 381;
    public const CYCLOCROSS = 382;
    public const CYCLOTOURISME = 383;
    public const VELO_SUR_PISTE = 384;
    public const VELO_URBAIN = 386;
    public const VELO_RANDONNEE = 387;
    public const VTT_CROSS_COUNTRY = 389;
    public const VTT_DESCENTE = 390;
    public const VTT_DIRT = 391;
    public const VTT_TRIAL = 392;
    public const VTT_ENDURO = 393;
    public const VTT_ALL_MOUNTAIN = 394;
    public const ZUMBA = 403;

    protected const MATCHING_ICONS = [
        self::HOCKEY => self::HOCKEY_SUR_GLACE,
        self::SKI_ALPIN => self::SKI,
        self::ALPINISME => self::ESCALADE,
        self::MARCHE => self::MARCHE_NORDIQUE,
    ];

    /**
     * @var int
     * @Groups({"sport_norm"})
     */
    public $id;

    /**
     * @var array
     * @Groups({"sport_norm"})
     */
    public $translatedNames;

    /**
     * @var Universe
     * @Groups({"sport_norm"})
     */
    public $universe;

    /**
     * @var bool
     * @Groups({"sport_norm"})
     */
    public $active = true;

    /**
     * @var \DateTime
     * @Groups({"sport_norm"})
     */
    public $createdAt;

    /**
     * @var \DateTime
     * @Groups({"sport_norm"})
     */
    public $updatedAt;

    /**
     * @var array
     * @Groups({"sport_norm"})
     */
    public $userEquipments;

    public function getNameByLocale(string $locale): ?string
    {
        return $this->getTranslatedPropertyByLocale('translatedNames', $locale);
    }

    public static function getIcon($id): string
    {
        $id = (int) $id;
        if (isset(self::MATCHING_ICONS[$id])) {
            return 'sports'.self::MATCHING_ICONS[$id];
        }

        return 'sports'.$id;
    }
}
