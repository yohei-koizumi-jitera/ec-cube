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

namespace Eccube\Controller\Admin\Customer;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Customer;
use Eccube\Entity\CustomerAddress;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Form\Type\Front\CustomerAddressType;
use Eccube\Repository\CustomerAddressRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class CustomerDeliveryEditController extends AbstractController
{
    protected CustomerAddressRepository $customerAddressRepository;

    public function __construct(CustomerAddressRepository $customerAddressRepository)
    {
        $this->customerAddressRepository = $customerAddressRepository;
    }

    /**
     * お届け先編集画面.
     *
     * @Route("/%eccube_admin_route%/customer/{id}/delivery/new", name="admin_customer_delivery_new", requirements={"id" = "\d+"}, methods={"GET", "POST"})
     * @Route("/%eccube_admin_route%/customer/{id}/delivery/{did}/edit", name="admin_customer_delivery_edit", requirements={"id" = "\d+", "did" = "\d+"}, methods={"GET", "POST"})
     *
     * @Template("@admin/Customer/delivery_edit.twig")
     */
    public function edit(Request $request, Customer $Customer, ?int $did = null)
    {
        $CustomerAddress = $this->getCustomerAddressForEdit($Customer, $did);

        $builder = $this->formFactory->createBuilder(CustomerAddressType::class, $CustomerAddress);

        $event = new EventArgs(
            [
                'builder' => $builder,
                'Customer' => $Customer,
                'CustomerAddress' => $CustomerAddress,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::ADMIN_CUSTOMER_DELIVERY_EDIT_INDEX_INITIALIZE);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            log_info('お届け先登録開始', [$did]);

            $this->entityManager->persist($CustomerAddress);
            $this->entityManager->flush();

            log_info('お届け先登録完了', [$did]);

            $event = new EventArgs(
                [
                    'form' => $form,
                    'Customer' => $Customer,
                    'CustomerAddress' => $CustomerAddress,
                ],
                $request
            );
            $this->eventDispatcher->dispatch($event, EccubeEvents::ADMIN_CUSTOMER_DELIVERY_EDIT_INDEX_COMPLETE);

            $this->addSuccess('admin.common.save_complete', 'admin');

            return $this->redirect($this->generateUrl('admin_customer_delivery_edit', [
                'id' => $Customer->getId(),
                'did' => $CustomerAddress->getId(),
            ]));
        }

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'CustomerAddress' => $CustomerAddress,
        ];
    }

    /**
     * お届け先情報取得処理（追加／編集）.
     * 追加時は最大件数を超えていないかチェックも含む
     */
    private function getCustomerAddressForEdit(Customer $Customer, ?int $did): CustomerAddress
    {
        if (is_null($did)) {
            // 新規追加
            $addressCurrNum = count($Customer->getCustomerAddresses());
            $addressMax = $this->eccubeConfig['eccube_deliv_addr_max'];
            if ($addressCurrNum >= $addressMax) {
                throw new NotFoundHttpException('最大お届け先件数を超えています。');
            }
            $CustomerAddress = new CustomerAddress();
            $CustomerAddress->setCustomer($Customer);
            return $CustomerAddress;
        }

        // 編集
        $CustomerAddress = $this->customerAddressRepository->findOneBy([
            'id' => $did,
            'Customer' => $Customer,
        ]);
        if (!$CustomerAddress) {
            throw new NotFoundHttpException('指定されたお届け先情報が存在しません。');
        }
        return $CustomerAddress;
    }

    /**
     * お届け先削除.
     *
     * @Route("/%eccube_admin_route%/customer/{id}/delivery/{did}/delete", requirements={"id" = "\d+", "did" = "\d+"}, name="admin_customer_delivery_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Customer $Customer, int $did)
    {
        $this->isTokenValid();
        log_info('お届け先削除開始', [$did]);

        $CustomerAddress = $this->customerAddressRepository->find($did);
        if ($CustomerAddress === null) {
            throw new NotFoundHttpException('指定されたお届け先情報が存在しません。');
        }

        if ($CustomerAddress->getCustomer()->getId() !== $Customer->getId()) {
            $this->deleteMessage();
            return $this->redirect($this->generateUrl('admin_customer_edit', ['id' => $Customer->getId()]));
        }

        try {
            $this->customerAddressRepository->delete($CustomerAddress);
            $this->addSuccess('admin.common.delete_complete', 'admin');
        } catch (ForeignKeyConstraintViolationException $e) {
            log_error('お届け先削除失敗', [$e]);
            $message = trans('admin.common.delete_error_foreign_key', ['%name%' => trans('admin.customer.customer_address')]);
            $this->addError($message, 'admin');
        }

        log_info('お届け先削除完了', [$did]);

        $event = new EventArgs(
            [
                'Customer' => $Customer,
                'CustomerAddress' => $CustomerAddress,
            ],
            $request
        );
        $this->eventDispatcher->dispatch($event, EccubeEvents::ADMIN_CUSTOMER_DELIVERY_DELETE_COMPLETE);

        return $this->redirect($this->generateUrl('admin_customer_edit', ['id' => $Customer->getId()]));
    }
}