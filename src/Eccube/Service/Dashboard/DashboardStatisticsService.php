<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Service\Dashboard;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\ResultSetMapping;
use Eccube\Entity\Master\CustomerStatus;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Repository\CustomerRepository;
use Eccube\Repository\OrderRepository;
use Eccube\Repository\ProductRepository;

class DashboardStatisticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
        private CustomerRepository $customerRepository,
    ) {
    }

    /**
     * @return array<int, int|string>
     */
    public function getOrderEachStatus(array $excludes): array
    {
        $sql = 'SELECT
                    t1.order_status_id as status,
                    COUNT(t1.id) as count
                FROM
                    dtb_order t1
                WHERE
                    t1.order_status_id NOT IN (:excludes)
                GROUP BY
                    t1.order_status_id
                ORDER BY
                    t1.order_status_id';
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('status', 'status');
        $rsm->addScalarResult('count', 'count');
        $query = $this->entityManager->createNativeQuery($sql, $rsm);
        $query->setParameters([':excludes' => $excludes]);
        $result = $query->getResult();
        $orderArray = [];
        foreach ($result as $row) {
            $orderArray[$row['status']] = $row['count'];
        }

        return $orderArray;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getSalesByDay(\DateTime $dateTime, array $excludes): array
    {
        $dateTimeStart = clone $dateTime;
        $dateTimeStart->setTime(0, 0, 0, 0);

        $dateTimeEnd = clone $dateTimeStart;
        $dateTimeEnd->modify('+1 days');

        return $this->getSalesBetween($dateTimeStart, $dateTimeEnd, $excludes);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getSalesByMonth(\DateTime $dateTime, array $excludes): array
    {
        $dateTimeStart = clone $dateTime;
        $dateTimeStart->setTime(0, 0, 0, 0);
        $dateTimeStart->modify('first day of this month');

        $dateTimeEnd = clone $dateTime;
        $dateTimeEnd->setTime(0, 0, 0, 0);
        $dateTimeEnd->modify('first day of 1 month');

        return $this->getSalesBetween($dateTimeStart, $dateTimeEnd, $excludes);
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countNonStockProducts(): int
    {
        $qb = $this->productRepository->createQueryBuilder('p')
            ->select('count(DISTINCT p.id)')
            ->innerJoin('p.ProductClasses', 'pc')
            ->where('pc.stock_unlimited = :StockUnlimited AND pc.stock = 0')
            ->andWhere('pc.visible = :visible')
            ->setParameter('StockUnlimited', false)
            ->setParameter('visible', true);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countProducts(): int
    {
        $qb = $this->productRepository->createQueryBuilder('p')
            ->select('count(p.id)')
            ->where('p.Status in (:Status)')
            ->setParameter('Status', [ProductStatus::DISPLAY_SHOW, ProductStatus::DISPLAY_HIDE]);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countCustomers(): int
    {
        $qb = $this->customerRepository->createQueryBuilder('c')
            ->select('count(c.id)')
            ->where('c.Status = :Status')
            ->setParameter('Status', CustomerStatus::REGULAR);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function getSalesBetween(\DateTime $dateTimeStart, \DateTime $dateTimeEnd, array $excludes): array
    {
        $qb = $this->orderRepository
            ->createQueryBuilder('o')
            ->select('
            SUM(o.payment_total) AS order_amount,
            COUNT(o) AS order_count')
            ->setParameter(':excludes', $excludes)
            ->setParameter(':targetDateStart', $dateTimeStart)
            ->setParameter(':targetDateEnd', $dateTimeEnd)
            ->andWhere(':targetDateStart <= o.order_date and o.order_date < :targetDateEnd')
            ->andWhere('o.OrderStatus NOT IN (:excludes)');
        $q = $qb->getQuery();

        try {
            return $q->getSingleResult();
        } catch (NoResultException) {
            return [];
        }
    }
}
