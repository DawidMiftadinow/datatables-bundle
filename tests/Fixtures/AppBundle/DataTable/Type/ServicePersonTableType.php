<?php

/*
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Fixtures\AppBundle\DataTable\Type;

use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Adapter\Doctrine\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Component\Routing\RouterInterface;
use Tests\Fixtures\AppBundle\Entity\Company;
use Tests\Fixtures\AppBundle\Entity\Person;

/**
 * ServicePersonTableType.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
class ServicePersonTableType implements DataTableTypeInterface
{
    /** @var RouterInterface */
    private $router;

    /**
     * ServicePersonTableType constructor.
     *
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(DataTable $dataTable, array $options)
    {
        $dataTable
            ->add('id', TextColumn::class, ['globalSearchable' => false])
            ->add('firstName', TextColumn::class, ['label' => 'name'])
            ->add('lastName', TextColumn::class)
            ->add('fullName', TextColumn::class, ['label' => 'fullName'])
            ->add('company', TextColumn::class, ['label' => 'employer', 'field' => 'company.name'])
            ->add('link', TextColumn::class, [
                'data' => function (Person $person) {
                    return sprintf('<a href="%s">%s, %s</a>', $this->router->generate('home'), $person->getLastName(), $person->getFirstName());
                },
            ])
            ->setTransformer(function ($row, Person $person) {
                $row['fullName'] = sprintf('%s (%s)', $person->getLastName(), $person->getCompany()->getName());

                return $row;
            })
            ->createAdapter(ORMAdapter::class, [
                'entity' => Person::class,
                'criteria' => [
                    new SearchCriteriaProvider(),
                ],
            ])
        ;
    }
}
