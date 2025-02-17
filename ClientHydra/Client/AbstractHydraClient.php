<?php

declare(strict_types=1);

namespace SportTrackingDataSdk\ClientHydra\Client;

use Doctrine\Common\Inflector\Inflector;
use SportTrackingDataSdk\ClientHydra\Adapter\HttpAdapterInterface;
use SportTrackingDataSdk\ClientHydra\Adapter\JsonResponse;
use SportTrackingDataSdk\ClientHydra\Adapter\Request;
use SportTrackingDataSdk\ClientHydra\Adapter\ResponseInterface;
use SportTrackingDataSdk\ClientHydra\Exception\ClientHydraException;
use SportTrackingDataSdk\ClientHydra\Exception\FormatException;
use SportTrackingDataSdk\ClientHydra\Metadata\MetadataManager;
use SportTrackingDataSdk\ClientHydra\Proxy\ProxyCollection;
use SportTrackingDataSdk\ClientHydra\Proxy\ProxyObject;
use SportTrackingDataSdk\ClientHydra\Utils\HydraParser;
use SportTrackingDataSdk\ClientHydra\Utils\IriConverter;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractHydraClient implements HydraClientInterface
{
    private static $cache_initialized = false;

    protected $adapter;
    protected $iriConverter;
    protected $serializer;
    protected $metadataManager;

    /** @var ProxyObject[] */
    private $objects = [];

    public function __construct(
        HttpAdapterInterface $adapter,
        IriConverter $iriConverter,
        SerializerInterface $serializer,
        MetadataManager $metadataManager
    ) {
        $this->adapter = $adapter;
        $this->iriConverter = $iriConverter;
        $this->serializer = $serializer;
        $this->metadataManager = $metadataManager;

        ProxyObject::_init(
            // refresh
            function (ProxyObject $proxyObject, $data): void {
                $this->serializer->deserialize(\json_encode($data), \get_class($proxyObject), 'json', [
                    'object_to_populate' => $proxyObject,
                    'groups' => [HydraParser::getDenormContext($data)],
                ]);
            },
            // getData
            function (ProxyObject $proxyObject) use ($metadataManager): array {
                $request = new Request(
                    'GET',
                    $this->iriConverter->getIriFromObject($proxyObject)
                );
                if (($metadata = $metadataManager->getClassMetadata(\get_class($proxyObject)))->isPersistantCacheEnable()) {
                    $request->setPersistantCacheEnable(true);
                    $request->setPersistantCacheScope($metadata->getPersistantCacheScope());
                    $request->setPersistantCacheTTL($metadata->getPersistantCacheTTL());
                }
                $requestResponse = $this->getAdapter()->call($request);

                if (!$requestResponse instanceof JsonResponse) {
                    throw new \RuntimeException('Cannot hydrate object with non json response');
                }

                return $requestResponse->getContent();
            },
            // getObject
            function ($className, $id) {
                return $this->getObject($className, $id, false);
            },
            // metadatamanager
            $metadataManager
        );

        ProxyCollection::_init(
            // getData
            function (?string $classname, string $uri, bool $executionCacheEnable = true) use ($metadataManager): array {
                $request = new Request(
                    'GET',
                    $uri
                );
                if (null !== $classname && ($metadata = $metadataManager->getClassMetadata($classname))->isPersistantCacheEnable()) {
                    $request->setPersistantCacheEnable(true);
                    $request->setPersistantCacheScope($metadata->getPersistantCacheScope());
                    $request->setPersistantCacheTTL($metadata->getPersistantCacheTTL());
                }
                $requestResponse = $this->getAdapter()->call($request, $executionCacheEnable);

                if (!$requestResponse instanceof JsonResponse) {
                    throw new \RuntimeException('Cannot hydrate object with non json response');
                }

                return $requestResponse->getContent();
            },
            // getProxyFromIri
            function (string $iri): ?ProxyObject {
                return $this->getProxyFromIri($iri);
            }
        );
    }

    public function cacheWarmUp(): void
    {
        if (self::$cache_initialized) {
            return;
        }
        self::$cache_initialized = true;

        $cacheData = [];
        foreach ($this->metadataManager->getClassMetadatas() as $id => $classMetadata) {
            if ($classMetadata->isPersistantCacheWarmup()) {
                $cacheData[] = [
                    'classname' => $classMetadata->getClass(),
                    'ttl' => $classMetadata->getPersistantCacheTTL(),
                    'fetchData' => function ($className): void {
                        $this->getCollection($className, [], false, true);
                    },
                ];
            }
        }

        foreach ($this->getAdapter()->warmupCache($cacheData) as $data) {
            $this->parseResponse($data['response']);
        }
    }

    public function contains($object): bool
    {
        return \in_array($object, $this->objects, true);
    }

    public function getProxyFromIri(string $iri, ?bool $autoHydrate = false): ?ProxyObject
    {
        $this->cacheWarmUp();

        // check if object not already store
        if (!isset($this->objects[$iri])) {
            $className = $this->iriConverter->getClassnameFromIri($iri);
            /** @var ProxyObject $proxyObject */
            $id = $this->iriConverter->getObjectIdFromIri($iri);
            $proxyObject = new $className();
            $proxyObject->setId($id);
            $this->objects[$iri] = $proxyObject;
        } else {
            $proxyObject = $this->objects[$iri];
        }

        if (true === $autoHydrate) {
            $proxyObject->_hydrate();
        }

        return $proxyObject;
    }

    protected function getIriFromObject(ProxyObject $proxyObject): ?string
    {
        return $this->iriConverter->getIriFromObject($proxyObject);
    }

    /**
     * @return $className
     */
    public function getObject(string $className, $id, ?bool $autoHydrate = false): ?ProxyObject
    {
        if (!\is_string($id) || !$this->iriConverter->isIri($id)) {
            $id = $this->iriConverter->getIriFromClassNameAndId($className, $id);
        }

        return $this->getProxyFromIri($id, $autoHydrate);
    }

    /**
     * @return null|ProxyCollection|ProxyObject
     */
    protected function parseResponse(ResponseInterface $response)
    {
        if (!$response instanceof JsonResponse || !isset(($elt = $response->getContent())['@type'])) {
            return null;
        }

        /* Collection case */
        if ('hydra:Collection' === $elt['@type']) {
            return new ProxyCollection(null, $elt);
        }

        /* Object case */
        if (!$elt['@id']) {
            throw new \RuntimeException('Method getObjectFromResponse only support object or collection');
        }

        $object = $this->getProxyFromIri($elt['@id'], false);
        if (null === $object) {
            throw new \RuntimeException(\sprintf('Cannot create object with iri : %s', $elt['@id']));
        }

        $object->_refresh($elt);

        return $object;
    }

    public function getCollection(
        string $classname,
        array $filters = [],
        bool $cacheEnable = true,
        bool $loadAll = false,
        bool $autoHydrateEnable = true
    ): ProxyCollection {
        $this->cacheWarmUp();

        $collection = new ProxyCollection(
            $classname,
            [
                'hydra:view' => [
                    'hydra:next' => $this->iriConverter->generateCollectionUri($classname, $filters),
                ],
            ],
            $cacheEnable,
            $autoHydrateEnable
        );

        if ($loadAll) {
            foreach ($collection as $i) {
            }
        }

        return $collection;
    }

    public function putObject(ProxyObject $object): ProxyObject
    {
        if (!$this->contains($object)) {
            throw new \RuntimeException('Object must be registered in HydraClient before using PUT on it');
        }

        $putData = $this->serializer->serialize(
            $object,
            'json',
            ['groups' => [HydraParser::getNormContext($object)], 'classContext' => \get_class($object), 'putContext' => true]
        );

        // no changes : ignore !
        if ('[]' === $putData) {
            return $object;
        }

        $response = $this->adapter->makeRequest(
            'PUT',
            $this->iriConverter->getIriFromObject($object),
            [],
            $putData
        );

        if (!$response instanceof JsonResponse) {
            throw new \RuntimeException('Error during update object');
        }

        $object->_refresh($response->getContent());

        return $object;
    }

    public function deleteObject(...$objectOrId): void
    {
        if (1 === \count($objectOrId) && $objectOrId[0] instanceof ProxyObject) {
            $iri = $this->iriConverter->getIriFromObject($objectOrId[0]);
        } elseif (2 === \count($objectOrId)) {
            $iri = $this->iriConverter->getIriFromClassNameAndId($objectOrId[0], $objectOrId[1]);
        } else {
            throw new \RuntimeException('Invalid input for deleteObject method');
        }

        $this->adapter->call(new Request('DELETE', $iri));

        unset($this->objects[$iri]);
    }

    public function postObject(ProxyObject $object): ProxyObject
    {
        $response = $this->adapter->makeRequest(
            'POST',
            $this->iriConverter->getCollectionIriFromClassName(\get_class($object)),
            [],
            $this->serializer->serialize(
                $object,
                'json',
                ['groups' => [HydraParser::getNormContext($object)], 'classContext' => \get_class($object)]
            )
        );

        if (!$response instanceof JsonResponse) {
            throw new \RuntimeException('Error during update object');
        }

        $object->setId($response->getContent()['id']);
        $object->_refresh($response->getContent());

        return $object;
    }

    public function getAdapter(): HttpAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @throws ClientHydraException
     *
     * @deprecated
     */
    public function __call(string $method, array $args)
    {
        if (1 !== \preg_match('/^(?<method>[a-z]+)(?<className>[A-Za-z]+)$/', $method, $matches)) {
            throw new FormatException(\sprintf('The method %s is not recognized.', $method));
        }

        $method = \strtolower($matches['method']);
        $className = $this->iriConverter->getEntityNamespace().'\\'.Inflector::singularize($matches['className']);

        switch ($method) {
            case 'get':
                // collection case
                if (!isset($args[0]) || \is_array($args[0])) {
                    return $this->getCollection($className, $args[0]['filters'] ?? []);
                }

                // item (string | int) case
                if (\is_int($args[0]) || \is_string($args[0])) {
                    return $this->getObject($className, $args[0], false);
                }

                throw new \RuntimeException('Unknown error during call get');
            case 'put':
                if (!$args[0] instanceof ProxyObject) {
                    throw new \RuntimeException('Put require a proxy object in parameter');
                }

                return $this->putObject($args[0]);
            case 'delete':
                if (!\is_string($args[0]) || !\is_int($args[0])) {
                    throw new \RuntimeException('Delete require a string or an int in parameter');
                }

                $this->deleteObject($className, $args[0]);

                return null;
            case 'post':
                if (!$args[0] instanceof ProxyObject) {
                    throw new \RuntimeException('Post require a proxy object in parameter');
                }

                return $this->postObject($args[0]);
        }

        throw new \RuntimeException('Cannot determine method to call');
    }
}
