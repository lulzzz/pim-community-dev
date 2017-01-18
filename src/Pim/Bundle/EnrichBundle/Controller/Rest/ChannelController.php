<?php

namespace Pim\Bundle\EnrichBundle\Controller\Rest;

use Akeneo\Bundle\MeasureBundle\Manager\MeasureManager;
use Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Component\StorageUtils\Remover\RemoverInterface;
use Akeneo\Component\StorageUtils\Saver\SaverInterface;
use Pim\Bundle\CatalogBundle\Entity\Channel;
use Pim\Component\Catalog\AttributeTypes;
use Pim\Component\Catalog\Model\ChannelInterface;
use Pim\Component\Catalog\Repository\AttributeRepositoryInterface;
use Pim\Component\Catalog\Repository\ChannelRepositoryInterface;
use Pim\Component\Catalog\Updater\ChannelUpdater;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Channel controller responsible for internal api requests
 *
 * @author    Filips Alpe <filips@akeneo.com>
 * @author    Alexandr Jeliuc <alex@jeliuc.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ChannelController
{
    /** @var ChannelRepositoryInterface */
    protected $channelRepository;

    /** @var NormalizerInterface */
    protected $normalizer;

    /** @var ChannelUpdater */
    protected $updater;

    /** @var SaverInterface */
    protected $saver;

    /** @var RemoverInterface */
    protected $remover;

    /** @var SimpleFactoryInterface  */
    protected $channelFactory;

    /** @var ValidatorInterface */
    protected $validator;

    /**
     * @param ChannelRepositoryInterface    $channelRepository
     * @param NormalizerInterface           $normalizer
     * @param ChannelUpdater                $updater
     * @param SaverInterface                $saver
     * @param RemoverInterface              $remover
     * @param SimpleFactoryInterface        $channelFactory
     * @param ValidatorInterface            $validator
     */
    public function __construct(
        ChannelRepositoryInterface $channelRepository,
        NormalizerInterface $normalizer,
        ChannelUpdater $updater,
        SaverInterface $saver,
        RemoverInterface $remover,
        SimpleFactoryInterface $channelFactory,
        ValidatorInterface $validator
    ) {
        $this->channelRepository = $channelRepository;
        $this->normalizer = $normalizer;
        $this->updater = $updater;
        $this->saver = $saver;
        $this->remover = $remover;
        $this->channelFactory = $channelFactory;
        $this->validator = $validator;
    }

    /**
     * Lists all channels
     *
     * @return JsonResponse
     */
    public function indexAction()
    {
        $channels = $this->channelRepository->findAll();

        $normalizedChannels = $this->normalizer->normalize($channels, 'internal_api');

        return new JsonResponse($normalizedChannels);
    }

    /**
     * Gets channel by code value
     *
     * @param string $identifier
     *
     * @return JsonResponse
     */
    public function getAction($identifier)
    {
        $channel = $this->getChannel($identifier);

        return new JsonResponse(
            $this->normalizer->normalize($channel, 'internal_api')
        );
    }

    /**
     * Saves new channel
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function postAction(Request $request)
    {
        $channel = $this->channelFactory->create();

        return $this->saveChannel($channel, $request);
    }

    /**
     * Updates channel
     *
     * @param Request $request
     * @param string  $code
     *
     * @return JsonResponse
     */
    public function putAction(Request $request, $code)
    {
        $channel = $this->getChannel($code);

        return $this->saveChannel($channel, $request);
    }

    /**
     * Removes channel
     *
     * @param $code
     *
     * @return JsonResponse
     */
    public function removeAction($code)
    {
        $channel = $this->getChannel($code);
        $this->remover->remove($channel);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param $code
     *
     * @return object
     */
    private function getChannel($code)
    {
        $channel = $this->channelRepository->findOneBy(
            [
                'code' => $code,
            ]
        );

        if (null === $channel) {
            throw new NotFoundHttpException(
                sprintf('Channel with code %s does not exist.', $code)
            );
        }

        return $channel;
    }

    /**
     * @param ChannelInterface $channel
     * @param Request          $request
     *
     * @return JsonResponse
     */
    private function saveChannel($channel, $request)
    {
        $data = json_decode($request->getContent(), true);
        $this->updater->update($channel, $data);

        $violations = $this->validator->validate($channel);

        if (0 < $violations->count()) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()] = [
                    'message' => $violation->getMessage()
                ];
            }

            return new JsonResponse($errors, 400);
        }

        $this->saver->save($channel);

        return new JsonResponse(
            $this->normalizer->normalize(
                $channel,
                'internal_api'
            )
        );
    }
}
