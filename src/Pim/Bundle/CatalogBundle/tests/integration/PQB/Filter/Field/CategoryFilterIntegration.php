<?php

namespace Pim\Bundle\CatalogBundle\tests\integration\PQB\Filter;

use Akeneo\Component\StorageUtils\Cursor\CursorInterface;
use Pim\Bundle\CatalogBundle\tests\integration\PQB\AbstractProductQueryBuilderTestCase;
use Pim\Component\Catalog\Model\ProductInterface;
use Pim\Component\Catalog\Model\ProductModelInterface;
use Pim\Component\Catalog\Query\Filter\Operators;

/**
 * @author    Marie Bochu <marie.bochu@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CategoryFilterIntegration extends AbstractProductQueryBuilderTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->createProduct('foo', ['categories' => ['categoryA1', 'categoryB']]);
        $this->createProduct('bar', []);
        $this->createProduct('baz', []);

        $this->createProductModel(['code' => 'root_product_model', 'family_variant' => 'familyVariantA1']);
        $this->createProductModel([
                'code'           => 'sub_product_model_1',
                'family_variant' => 'familyVariantA1',
                'parent'         => 'root_product_model',
                'categories'     => ['categoryC'],
                'values'         => [
                    'a_simple_select' => [
                        ['data' => 'optionB', 'locale' => null, 'scope' => null],
                    ],
                ],
            ]
        );
        $this->createProductModel([
                'code'           => 'sub_product_model_2',
                'family_variant' => 'familyVariantA1',
                'categories'     => ['categoryC'],
                'parent'         => 'root_product_model',
                'values'         => [
                    'a_simple_select' => [
                        ['data' => 'optionA', 'locale' => null, 'scope' => null],
                    ],
                ],
            ]
        );
        $this->createVariantProduct('variant_product_1',
            [
                'family'     => 'familyA',
                'parent'     => 'sub_product_model_1',
                'values'     => [
                    'a_yes_no' => [
                        ['data' => false, 'locale' => null, 'scope' => null],
                    ],
                ],
            ]
        );
        $this->createVariantProduct('variant_product_2',
            [
                'family'     => 'familyA',
                'parent'     => 'sub_product_model_2',
                'values'     => [
                    'a_yes_no' => [
                        ['data' => true, 'locale' => null, 'scope' => null],
                    ],
                ],
            ]
        );
    }

    public function testOperatorIn()
    {
        $result = $this->executeFilter([['categories', Operators::IN_LIST, ['master']]]);
        $this->assert($result, []);

        $result = $this->executeFilter([['categories', Operators::IN_LIST, ['categoryA1', 'categoryA2']]]);
        $this->assert($result, ['foo']);
    }

    public function testOperatorNotIn()
    {
        $result = $this->executeFilter([['categories', Operators::NOT_IN_LIST, ['master']]]);
        $this->assert(
            $result,
            [
                'bar',
                'baz',
                'foo',
                'root_product_model',
                'sub_product_model_1',
                'sub_product_model_2',
                'variant_product_1',
                'variant_product_2',
            ]
        );

        $result = $this->executeFilter([['categories', Operators::NOT_IN_LIST, ['categoryA1', 'categoryA2']]]);
        $this->assert(
            $result,
            [
                'bar',
                'baz',
                'root_product_model',
                'sub_product_model_1',
                'sub_product_model_2',
                'variant_product_1',
                'variant_product_2',
            ]
        );
    }

    public function testOperatorUnclassified()
    {
        $result = $this->executeFilter([['categories', Operators::UNCLASSIFIED, []]]);
        $this->assert($result, ['bar', 'baz']);
    }

    public function testOperatorInOrUnclassified()
    {
        $result = $this->executeFilter([['categories', Operators::IN_LIST_OR_UNCLASSIFIED, ['categoryB']]]);
        $this->assert($result, ['bar', 'baz', 'foo']);

        $result = $this->executeFilter([['categories', Operators::IN_LIST_OR_UNCLASSIFIED, ['master']]]);
        $this->assert($result, ['bar', 'baz']);
    }

    public function testOperatorInOrUnclassifiedInTwoDifferentFilters()
    {
        $this->createProduct('qux', ['categories' => ['categoryA1']]);

        $result = $this->executeFilter([
            ['categories', Operators::IN_LIST_OR_UNCLASSIFIED, ['categoryB']],
            ['categories', Operators::IN_LIST_OR_UNCLASSIFIED, ['categoryA1']],
        ]);
        $this->assert($result, ['bar', 'baz', 'foo']);
    }

    public function testOperatorInChildren()
    {
        $result = $this->executeFilter([['categories', Operators::IN_CHILDREN_LIST, ['master']]]);
        $this->assert($result, ['foo']);

        $result = $this->executeFilter([['categories', Operators::IN_CHILDREN_LIST, ['categoryA1']]]);
        $this->assert($result, ['foo']);
    }

    public function testOperatorNotInChildren()
    {
        $result = $this->executeFilter([['categories', Operators::NOT_IN_CHILDREN_LIST, ['master']]]);
        $this->assert($result, ['bar', 'baz']);
    }

    public function testOperatorInWithVariantProducts()
    {
        $result = $this->executeFilter([['categories', Operators::IN_LIST, ['categoryC']]]);
        $this->assert($result, ['sub_product_model_1', 'sub_product_model_2']);
    }

    /**
     * @expectedException \Pim\Component\Catalog\Exception\UnsupportedFilterException
     * @expectedExceptionMessage Filter on property "categories" is not supported or does not support operator ">="
     */
    public function testErrorOperatorNotSupported()
    {
        $this->executeFilter([['categories', Operators::GREATER_OR_EQUAL_THAN, ['categoryA1']]]);
    }

    /**
     * @param array $data
     */
    private function createProductModel(array $data)
    {
        $productModel = $this->get('pim_catalog.factory.product_model')->create();
        $this->get('pim_catalog.updater.product_model')->update(
            $productModel,
            $data
        );

        $violations = $this->get('validator')->validate($productModel);
        $this->assertEquals(0, $violations->count());

        $this->get('pim_catalog.saver.product_model')->save($productModel);

        $this->get('akeneo_elasticsearch.client.product')->refreshIndex();
    }

    /**
     * @param string $identifier
     * @param array  $data
     */
    protected function createVariantProduct(string $identifier, array $data = [])
    {
        $product = $this->get('pim_catalog.builder.variant_product')->createProduct($identifier);
        $this->get('pim_catalog.updater.product')->update($product, $data);
        $constraintList = $this->get('pim_catalog.validator.product')->validate($product);
        $this->assertEquals(0, $constraintList->count());
        $this->get('pim_catalog.saver.product')->save($product);
        $this->get('akeneo_elasticsearch.client.product')->refreshIndex();
    }

    /**
     * @param array $filters
     *
     * @return CursorInterface
     */
    protected function executeFilter(array $filters)
    {
        $pqb = $this->get('pim_enrich.query.product_and_product_model_query_builder_from_size_factory')->create(
            ['limit' => 100]
        );

        foreach ($filters as $filter) {
            $context = isset($filter[3]) ? $filter[3] : [];
            $pqb->addFilter($filter[0], $filter[1], $filter[2], $context);
        }

        return $pqb->execute();
    }

    /**
     * @param CursorInterface $result
     * @param array           $expected
     */
    protected function assert(CursorInterface $result, array $expected)
    {
        $entities = [];
        foreach ($result as $entity) {
            if ($entity instanceof ProductInterface) {
                $entities[] = $entity->getIdentifier();
            } elseif ($entity instanceof ProductModelInterface) {
                $entities[] = $entity->getCode();
            }
        }

        sort($entities);
        sort($expected);

        $this->assertSame($expected, $entities);
    }
}
