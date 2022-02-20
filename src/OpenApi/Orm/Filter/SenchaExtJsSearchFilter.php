<?php

namespace CoralMedia\Bundle\FrameworkBundle\OpenApi\Orm\Filter;

use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterTrait;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\FilterValidationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class SenchaExtJsSearchFilter extends AbstractContextAwareFilter implements SearchFilterInterface
{
    use SearchFilterTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        ?RequestStack $requestStack,
        IriConverterInterface $iriConverter,
        PropertyAccessorInterface $propertyAccessor = null,
        LoggerInterface $logger = null,
        array $properties = null,
        IdentifiersExtractorInterface $identifiersExtractor = null,
        NameConverterInterface $nameConverter = null
    ) {
        parent::__construct($managerRegistry, $requestStack, $logger, $properties, $nameConverter);

        if (null === $identifiersExtractor) {
            @trigger_error(
                'Not injecting ItemIdentifiersExtractor is deprecated 
                since API Platform 2.5 and can lead to unexpected behaviors, it 
                will not be possible anymore in API Platform 3.0.',
                \E_USER_DEPRECATED
            );
        }

        $this->iriConverter = $iriConverter;
        $this->identifiersExtractor = $identifiersExtractor;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param string $property
     * @param mixed $value
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param string|null $operationName
     * @throws FilterValidationException
     */
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null
    ) {
        if ($property !== 'filters') { // otherwise filter is applied to order and page as well
            return;
        }

        $filters = json_decode($value, true);

        foreach ($filters as $filter) {
            if (
                !$this->isPropertyEnabled($filter['property'], $resourceClass) ||
                !$this->isPropertyMapped($filter['property'], $resourceClass)
            ) {
                throw new FilterValidationException(
                    [sprintf('Property `%s` is not mapped or has no filter enabled', $filter['property'])],
                );
            }
            // Generate a unique parameter name to avoid collisions with other filters
            $parameterName = $queryNameGenerator->generateParameterName($filter['property']);

            switch ($filter['operator']) {
                case 'like':
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->like('o.' . $filter['property'], "'%{$filter['value']}%'")
                    );
                    break;
                default:
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->eq('o.' . $filter['property'], $filter['value'])
                    );
                    break;
            }
        }
    }

    protected function getType(string $doctrineType): string
    {
        switch ($doctrineType) {
            case Types::ARRAY:
                return 'array';
            case Types::BIGINT:
            case Types::INTEGER:
            case Types::SMALLINT:
                return 'int';
            case Types::BOOLEAN:
                return 'bool';
            case Types::DATE_MUTABLE:
            case Types::TIME_MUTABLE:
            case Types::DATETIME_MUTABLE:
            case Types::DATETIMETZ_MUTABLE:
            case Types::DATE_IMMUTABLE:
            case Types::TIME_IMMUTABLE:
            case Types::DATETIME_IMMUTABLE:
            case Types::DATETIMETZ_IMMUTABLE:
                return \DateTimeInterface::class;
            case Types::FLOAT:
                return 'float';
        }

        return 'string';
    }

    protected function getIriConverter(): IriConverterInterface
    {
        return $this->iriConverter;
    }

    protected function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor;
    }
}
