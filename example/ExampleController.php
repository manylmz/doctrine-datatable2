<?php

namespace App\Controller;

use App\Service\DataTables\DataTableBuilder;
use EntityManagerInterface;
use Request;

class PageController extends Controller
{
  /**
     * @Route("/data", name="page_datatable")
     * @Method({"POST"})
     *
     * @param EntityManagerInterface $entity
     * @param DataTableBuilder $builder
     * @param Request $request
     * @return Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function data(EntityManagerInterface $entity, DataTableBuilder $builder, Request $request): Response
    {
        /** @var PageRepository $page */
        $page  = $this->loadRepository('Page');
        /** @var $query */
        $query = $page->getDataQuery();

        /** @var  $expr */
        $expr = $entity->getExpressionBuilder();
        /** @var $andX */
        $andX = (clone $expr)->andX();

        // set index column
        $builder->setIndexColumn($page->getClassName(), true);
        $builder->setRequest($request);

        $status = strtolower($request->get("status"));
        if (!empty($status) and strlen($status) > 0 and in_array($status, [Page::ACTIVE, Page::PASSIVE])) {
            $andX->add((clone $expr)->eq(
                "{$builder->getIndexColumn()}.status",
                sprintf("'%s'", $status)
            ));
        }

        if ($andX->count() > 0) {
            $query->where($andX);
        }

        return $builder->setQueryBuilder($query)->getJsonResponse();
    }
}
