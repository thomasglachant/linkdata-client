<?php

declare(strict_types=1);

namespace Stadline\LinkdataClient\ClientHydra\Proxy;

use Stadline\LinkdataClient\ClientHydra\Adapter\AdapterInterface;
use Stadline\LinkdataClient\ClientHydra\Adapter\JsonResponse;
use Stadline\LinkdataClient\ClientHydra\Type\FormatType;
use Stadline\LinkdataClient\ClientHydra\Utils\HydraParser;
use Stadline\LinkdataClient\ClientHydra\Utils\IriConverter;
use Symfony\Component\Serializer\SerializerInterface;

class ProxyManager
{
    private $adapter;
    private $iriConverter;
    private $serializer;

    /** @var ProxyObject[] */
    private $objects = [];

    public function __construct(
        AdapterInterface $adapter,
        IriConverter $iriConverter,
        SerializerInterface $serializer
    ) {
        $this->adapter = $adapter;
        $this->iriConverter = $iriConverter;
        $this->serializer = $serializer;
    }

    public function getObjectFromIri(string $iri): ?ProxyObject
    {
        $object = $this->getProxyFromIri($iri);
        if (null === $object) {
            return null;
        }

        $object->_hydrate();

        return $object;
    }

    public function getProxyFromIri(string $iri): ?ProxyObject
    {
        // check if object already store
        if (isset($this->objects[$iri])) {
            return $this->objects[$iri];
        }

        $className = $this->iriConverter->getClassnameFromIri($iri);
        $proxyObject = new $className(
            $this->iriConverter,
            $this->serializer,
            $this,
            $className,
            $this->iriConverter->getObjectIdFromIri($iri)
        );
        $this->objects[$iri] = $proxyObject;

        return $proxyObject;
    }

    public function getObject(string $className, $id): ?ProxyObject
    {
        $iri = $this->iriConverter->getIriFromClassNameAndId($className, $id);

        return $this->getObjectFromIri($iri);
    }

//    public function getObject(string $iri): ProxyObject
//    {
//        // check if object already store
//        if (isset($this->objects[$iri])) {
//            return $this->objects[$iri];
//        }
//
//        // resolve method to call.
//        $methodToCall = \ucfirst(Inflector::singularize(\explode('/', $iri)[2]));
//        $tempMethodToCall = 'get';
//
//        if (-1 !== \strstr('_', $methodToCall)) {
//            foreach (\explode('_', $methodToCall) as $part) {
//                if ('get' !== $part) {
//                    $tempMethodToCall .= \ucfirst(Inflector::singularize($part));
//                }
//            }
//
//            $methodToCall = $tempMethodToCall;
//        }
//
//        $id = \explode('/', $iri)[3];
//
//        // call client to resolve proxy.
//        $object = $this->hydraClient->send($methodToCall, [$id]);
//        $objects[$iri] = $object;
//
//        return $object;
//    }

    public function hasObject(string $iri): bool
    {
        // check if object already store
        return isset($this->objects[$iri]);
    }

    public function addObject(string $iri, ProxyObject $object): void
    {
        $this->objects[$iri] = $object;
    }

    public function getCollection(string $classname, array $filters = []): ProxyCollection
    {
        return new ProxyCollection(
            $this,
            $this->iriConverter,
            $classname,
            $filters
        );
    }

    public function putObject(ProxyObject $object): ProxyObject
    {
        $response = $this->adapter->makeRequest(
            'PUT',
            $this->iriConverter->getIriFromObject($object),
            [],
            $this->serializer->serialize(
                $object,
                FormatType::JSON,
                ['groups' => [HydraParser::getNormContext($object)], 'classContext' => \get_class($object)]
            )
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

        $this->adapter->makeRequest(
            'DELETE',
            $iri
        );

        unset($this->objects[$iri]);
    }

    public function postObject(ProxyObject $object): ProxyObject
    {
        $response = $this->adapter->makeRequest(
            'POST',
            $this->iriConverter->getCollectionIriFromClassName(\get_class($object)),
            $this->serializer->serialize(
                $object,
                FormatType::JSON,
                ['groups' => [HydraParser::getNormContext()]]
            )
        );

        if (!$response instanceof JsonResponse) {
            throw new \RuntimeException('Error during update object');
        }

        $object->_refresh($response->getContent());

        return $object;
    }

    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }
}
