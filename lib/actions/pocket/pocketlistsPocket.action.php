<?php

/**
 * Class pocketlistsPocketAction
 */
class pocketlistsPocketAction extends pocketlistsViewAction
{
    /**
     * @throws waDbException
     * @throws waException
     */
    public function execute()
    {
        $id = waRequest::get('id', 0, waRequest::TYPE_INT);
        $list_id = waRequest::get('list_id', false, waRequest::TYPE_INT);

        $available_pockets = pocketlistsRBAC::getAccessPocketForContact();
//        if ($id && !in_array($id, $available_pockets)) {
//            throw new waException('Access denied.', 403);
//        }

        $us = new pocketlistsUserSettings();
        $pm = new pocketlistsPocketModel();
        $lm = new pocketlistsListModel();

        $last_pocket_list_id = $us->getLastPocketList();

        if (!$id) {
            if (isset($last_pocket_list_id['pocket_id'])) { // last visited pocket
                $id = $last_pocket_list_id['pocket_id'];
            } else { // first of available pockets
                $id = reset($available_pockets);
            }
        }

        // check if user have access to this pocket/list
        if (!in_array($id, $available_pockets) ||
            (isset($last_pocket_list_id['pocket_id']) &&
                !in_array($last_pocket_list_id['pocket_id'], $available_pockets))
        ) {
            $id = reset($available_pockets);
        }

        /** @var pocketlistsPocketModel $pocketModel */
        $pocketModel = wa()->getConfig()->getModel(pocketlistsPocket::class);

        if (!$id) {
            $allPockets = $pocketModel->getAllPockets();
            $pocket = reset($allPockets);
        } else {
            /** @var pocketlistsPocket $pocket */
            $pocket = wa()->getConfig()
                ->getEntityFactory(pocketlistsPocket::class)
                ->findById($id);
        }

        $lists = $pocket->getUserLists();

        if (!$list_id) {
            if ($list_id < 0 && isset($last_pocket_list_id['list_id']) && $last_pocket_list_id['pocket_id'] == $pocket['id']) {
                $list_id = $last_pocket_list_id['list_id'];
            } else {
                if ($lists) {
                    $firtsList = reset($lists);
                    $list_id = $firtsList['id'];
                    $last_pocket_list_id = ["pocket_id" => $id, "list_id" => $list_id];
                } else {
                    $last_pocket_list_id = ["pocket_id" => $id];
                }
            }
        } else {
            $last_pocket_list_id = ["pocket_id" => $id, "list_id" => $list_id];
        }

        if ($list_id != -1) {
            $us->set('last_pocket_list_id', json_encode($last_pocket_list_id));
        }

        $lists_html = wao(new pocketlistsListAction(['list_id' => $list_id, 'pocket_id' => $pocket->getId()]))->display();
        $this->view->assign('lists_html', $lists_html);
        $this->view->assign(
            'isAdmin',
            pocketlistsRBAC::contactHasAccessToPocket($pocket->getId()) == pocketlistsRBAC::RIGHT_ADMIN ? 1 : 0
        );
        $this->view->assign('lists', $lists);
        $this->view->assign('list_id', $list_id);
        $this->view->assign('pocket', $pocket);
    }
}
