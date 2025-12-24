<?php

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
    private CustomerAddressRepository $customerAddressRepository;

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
        // お届け先情報を取得または新規作成
        $CustomerAddress = $this->resolveCustomerAddress($Customer, $did);

        // フォームを作成し、リクエストを処理
        $form = $this->formFactory
            ->createBuilder(CustomerAddressType::class, $CustomerAddress)
            ->getForm();
        $form->handleRequest($request);

        // フォームが送信され、かつ有効な場合
        if ($form->isSubmitted() && $form->isValid()) {
            log_info('お届け先登録開始', [$did]);
            // お届け先情報を保存
            $this->persistAddress($CustomerAddress);

            log_info('お届け先登録完了', [$did]);
            // 成功メッセージを追加
            $this->addSuccess('admin.common.save_complete', 'admin');
            // イベントをディスパッチ
            $this->dispatchEvent(EccubeEvents::ADMIN_CUSTOMER_DELIVERY_EDIT_INDEX_COMPLETE, $form, $Customer, $CustomerAddress, $request);

            // 編集画面にリダイレクト
            return $this->redirectToEdit($Customer, $CustomerAddress);
        }
        // 初期化イベントをディスパッチ
        $this->dispatchEvent(EccubeEvents::ADMIN_CUSTOMER_DELIVERY_EDIT_INDEX_INITIALIZE, null, $Customer, $CustomerAddress, $request);

        // テンプレートに渡すデータを返す
        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'CustomerAddress' => $CustomerAddress,
        ];
    }

    /**
     * お届け先情報取得・最大件数制限対応
     */
    private function resolveCustomerAddress(Customer $Customer, ?int $did): CustomerAddress
    {
        if ($did === null) {
            // お届け先の最大件数を超えている場合は例外を投げる
            if (count($Customer->getCustomerAddresses()) >= $this->eccubeConfig['eccube_deliv_addr_max']) {
                throw new NotFoundHttpException('最大お届け先件数を超えています。');
            }
            // 新しいお届け先情報を作成
            $CustomerAddress = new CustomerAddress();
            $CustomerAddress->setCustomer($Customer);
        } else {
            // 指定されたIDのお届け先情報を取得
            $CustomerAddress = $this->customerAddressRepository->findOneBy([
                'id' => $did,
                'Customer' => $Customer,
            ]);
            // お届け先情報が存在しない場合は例外を投げる
            if (!$CustomerAddress) {
                throw new NotFoundHttpException('指定されたお届け先情報が存在しません。');
            }
        }
        return $CustomerAddress;
    }

    /**
     * 共通：お届け先保存
     */
    private function persistAddress(CustomerAddress $address): void
    {
        // お届け先情報をデータベースに保存
        $this->entityManager->persist($address);
        $this->entityManager->flush();
    }

    /**
     * 共通：イベントディスパッチ
     */
    private function dispatchEvent(string $eventName, $form, Customer $Customer, CustomerAddress $CustomerAddress, Request $request)
    {
        $data = [
            'Customer' => $Customer,
            'CustomerAddress' => $CustomerAddress
        ];
        if ($form !== null) $data['form'] = $form;
        // イベントを作成し、ディスパッチ
        $event = new EventArgs($data, $request);
        $this->eventDispatcher->dispatch($event, $eventName);
    }

    /**
     * 共通：編集画面リダイレクト
     */
    private function redirectToEdit(Customer $Customer, CustomerAddress $CustomerAddress)
    {
        // 編集画面へのURLを生成し、リダイレクト
        return $this->redirect($this->generateUrl('admin_customer_delivery_edit', [
            'id' => $Customer->getId(),
            'did' => $CustomerAddress->getId(),
        ]));
    }

    /**
     * お届け先削除.
     *
     * @Route("/%eccube_admin_route%/customer/{id}/delivery/{did}/delete", requirements={"id" = "\d+", "did" = "\d+"}, name="admin_customer_delivery_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Customer $Customer, int $did)
    {
        // トークンの有効性を確認
        $this->isTokenValid();
        log_info('お届け先削除開始', [$did]);

        // 指定されたIDのお届け先情報を取得
        $CustomerAddress = $this->customerAddressRepository->find($did);
        // お届け先情報が存在しないか、顧客IDが一致しない場合
        if (!$CustomerAddress || $CustomerAddress->getCustomer()->getId() !== $Customer->getId()) {
            $this->deleteMessage();
            return $this->redirect($this->generateUrl('admin_customer_edit', ['id' => $Customer->getId()]));
        }

        try {
            // お届け先情報を削除
            $this->customerAddressRepository->delete($CustomerAddress);
            // 成功メッセージを追加
            $this->addSuccess('admin.common.delete_complete', 'admin');
        } catch (ForeignKeyConstraintViolationException $e) {
            // 外部キー制約違反の場合のエラーメッセージを追加
            log_error('お届け先削除失敗', [$e]);
            $message = trans('admin.common.delete_error_foreign_key', ['%name%' => trans('admin.customer.customer_address')]);
            $this->addError($message, 'admin');
        }

        log_info('お届け先削除完了', [$did]);
        // 削除完了イベントをディスパッチ
        $this->dispatchEvent(EccubeEvents::ADMIN_CUSTOMER_DELIVERY_DELETE_COMPLETE, null, $Customer, $CustomerAddress, $request);

        // 顧客編集画面にリダイレクト
        return $this->redirect($this->generateUrl('admin_customer_edit', ['id' => $Customer->getId()]));
    }
}