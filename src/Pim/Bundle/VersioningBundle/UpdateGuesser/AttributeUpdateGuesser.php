<?php

namespace Pim\Bundle\VersioningBundle\UpdateGuesser;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Attribute update guesser
 *
 * @author    Filips Alpe <filips@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeUpdateGuesser implements UpdateGuesserInterface
{
    /** @var ManagerRegistry */
    protected $registry;

    /** @var string */
    protected $productClass;

    /**
     * @param ManagerRegistry $registry
     * @param string          $productClass
     */
    public function __construct(ManagerRegistry $registry, $productClass)
    {
        $this->registry     = $registry;
        $this->productClass = $productClass;
    }

    /**
     * {@inheritdoc}
     */
    public function supportAction($action)
    {
        return $action === UpdateGuesserInterface::ACTION_DELETE;
    }

    /**
     * {@inheritdoc}
     */
    public function guessUpdates(EntityManager $em, $entity, $action)
    {
        //TODO: remove on 1.3
        return [];
    }
}
