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

namespace Eccube\Controller\Admin\Traits;

use Symfony\Component\HttpFoundation\Request;

trait PaginationTrait
{
    /**
     * Resolve the page count from request, session, and default value.
     *
     * Controllers using this trait must provide $session and $pageMaxRepository.
     */
    protected function resolvePageCount(
        Request $request,
        string $sessionKey,
        int $defaultPageCount
    ): int {
        $pageCount = (int) $this->session->get($sessionKey, $defaultPageCount);
        $pageCountParam = (int) $request->get('page_count');

        if ($pageCountParam) {
            $pageMaxis = $this->pageMaxRepository->findAll();
            foreach ($pageMaxis as $pageMax) {
                if ($pageCountParam == $pageMax->getName()) {
                    $pageCount = (int) $pageMax->getName();
                    $this->session->set($sessionKey, $pageCount);
                    break;
                }
            }
        }

        return $pageCount;
    }

    /**
     * Resolve the page number and persist it when pagination is requested.
     *
     * Controllers using this trait must provide $session.
     */
    protected function resolvePageNo(
        Request $request,
        string $sessionKey,
        ?int $pageNo
    ): int {
        if ($pageNo) {
            $this->session->set($sessionKey, $pageNo);

            return $pageNo;
        }

        if ($request->get('resume')) {
            return (int) $this->session->get($sessionKey, 1);
        }

        return 1;
    }
}
