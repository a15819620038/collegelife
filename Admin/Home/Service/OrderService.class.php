<?php
namespace Home\Service;

/**
* OrderService
*/
class OrderService extends CommonService {
    /**
     * 获取所有的订单
     * @return
     */
    public function getOrders() {
        $orders = D('Order')->relation(true)
                            ->order('id DESC')
                            ->select();

        return $orders;  
    }

    /**
     * 订单发货
     * @param  string $uuid
     * @return boolean
     */
    public function consignOrder($uuid) {
        $Order = M('Order');
        $order['consignment_at'] = datetime();
        $order['order_status'] = '已发货';
        // $order['admin_id'] = $_SESSION['id'];
        $where['uuid'] = $uuid;
        if (false === $Order->where($where)->save($order)) {
            return false;
        }

        // 订单日志
        $this->orderLog($uuid, $order['order_status']);

        return true;
    }

    /**
     * 订单付款
     * @param  string $uuid
     * @return boolean
     */
    public function payOrder($uuid) {
        $Order = D('Order');
        $where['uuid'] = $uuid;
        $order['payment_status'] = '已收款';
        $order['admin_id'] = $_SESSION['id'];
        $order['payment_at'] = datetime();

        if (false === $Order->where($where)->save($order)) {
            return false;
        }

        // 订单日志
        $this->orderLog($uuid, $order['payment_status']);

        // 分管理收款
        $this->orderTransaction($uuid, $_SESSION['id']);

        // 修改订单确认状态
        $this->confirm($uuid, 0);

        // 增加商品销量 更新用户已购信息
        $order = $Order->relation(true)->where($where)->find();
        $bought['user_id'] = $order['user_id'];
        foreach ($order['order_goods_ships'] as $subOrder) {
            $goodsId = $subOrder['goods_id'];
            $bought['goods_id'] = $goodsId;
            M('Goods')->where(array('id' => $goodsId))->setInc('sold_count');
            M('Bought')->add($bought);
        }

        return true;
    }

    /**
     * 获取订单的详情
     * @param  string $uuid
     * @return array  订单商品，商品数量，订单总价
     */
    public function getOrderDetail($orderUuid) {
        $uuid = sql_injection($orderUuid);
        $order = D('Order')->relation(true)
                           ->where(array('uuid' => $orderUuid))
                           ->find();

        // 无效的订单
        if (!$order) {
            return null;
        }

        // 获取所有订单中的商品
        $orderGoodsShips = $order['order_goods_ships'];
        foreach ($orderGoodsShips as $key => $orderGoodsShip) {
            $orderGoodsShip = D('OrderGoodsShip')
                               ->relation(true)
                               ->where(array('id' => $orderGoodsShip['id']))
                               ->find();
            $orderGoodsShip['goods']['count'] = $orderGoodsShip['goods_count'];
            $goods[] = $orderGoodsShip['goods'];
        }

        // 获取商品分类
        foreach ($goods as $key => $item) {
            $item = D('Goods')->relation(true)
                              ->where(array('id' => $item['id']))
                              ->find();
            $goods[$key]['category'] = $item['category']['name'];
        }

        unset($order['order_goods_ships']);
        $order['goods'] = $goods;

        return $order;
    }
    
    /**
     * 分管理员按楼栋数管理订单
     * @return array
     */
    public function getCount() {
        if ($_SESSION['rank'] == 3) {
            return parent::getCount();
        }

        // 获取管理员所管理的用户的id
        $userIds = $this->getUseridByAdmin($_SESSION['id']);

        // 用户的订单数
        $where['user_id'] = array('in', $userIds);
        $orderCnt = M('Order')->where($where)->count();

        return $orderCnt;
    }

    /**
     * 返回用户id数组
     * @param  int $id
     * @return array
     */
    private function getUseridByAdmin($id) {
        // 获取管理员所管理的用户
        $adminService = D('Admin', 'Service');
        $users = $adminService->getUserBelongsAdmin($id, array('id'));
        $userIds = array();
        foreach ($users as $user) {
            $userIds[] = $user['id'];
        }
        $userIds = implode(',', $userIds);

        return $userIds;
    }

    /**
     * 记录订单状态
     * @param  string $uuid     
     * @param  string $doStatus 
     * @return 
     */
    public function orderLog($uuid, $doStatus) {
        // 记录对订单的操作
        $order = M('Order')->where(array('uuid' => $uuid))->find();
        $doOrder['order_id'] = $order['id'];
        $doOrder['admin_id'] = $_SESSION['id'];
        $doOrder['do_status'] = $doStatus;
        $doOrder['modified_at'] = datetime();
        M('AdminDoOrder')->add($doOrder);
    }

    /**
     * 获得订单操作状态
     * @return array
     */
    public function getAdminDoOrder($uuid) {
        $order = M('Order')->where(array('uuid' => $uuid))
                           ->field(array('id'))
                           ->find();
        $AdminDoOrder = M('AdminDoOrder');
        $doOrders = $AdminDoOrder->where(array('order_id' => $order['id']))
                                 ->select();

        foreach ($doOrders as $key => $doOrder) {
            $admin = M('Admin')->where(array('id' => $doOrder['admin_id']))
                               ->field('admin_name')
                               ->find();
            $doOrders[$key]['admin_name'] = $admin['admin_name'];
        }

        return $doOrders;
    }

    /**
     * 分管理收款转账
     * @param  string $uuid
     * @return fixed
     */
    public function orderTransaction($uuid, $adminId) {
        $order = M('Order')->where(array('uuid' => $uuid))->find();
        
        $transaction['order_id'] = $order['id'];
        $transaction['admin_id'] = $adminId;
        $transaction['payment'] = $order['payment'];
        $transaction['payment_at'] = $order['payment_at'];

        return M('Transaction')->add($transaction);
    }

    /**
     * 修改订单确认状态
     * @return fixed
     */
    public function confirm($uuid, $status) {
        $Order = $this->getM();
        if (false === $Order->where(array('uuid' => $uuid))
                            ->save(array('confirm_status' => $status))) {
            return false;
        }

        // 记录日志
        if ($_SESSION['rank'] == 3) {
            $this->orderLog($uuid, '确认订单');
        }

        return true;
    }

    /**
     * 订单分配给指定的分管理
     * @param  string $uuid
     * @return fixed
     */
    public function assignTo($orderUUID, $adminUUID) {
        $admin = M('Admin')->where(array('uuid' => $adminUUID))->find();

        if (false === $this->getM()
                           ->where(array('uuid' => $orderUUID))
                           ->save(array('assign_to' => $admin['id']))) {
            return false;
        }

        // 记录日志
        $this->orderLog($orderUUID, "分配到 $admin[admin_name]");
    }

    /**
     * 
     * @param  int $firstRow
     * @param  int $listRows
     * @return array
     */
    public function getPagination($firstRow, $listRows) {
        if ($_SESSION['rank'] == 3) {
            return parent::getPagination($firstRow, $listRows);
        }

        $userIds = $this->getUseridByAdmin($_SESSION['id']);
        $where['user_id'] = array('in', $userIds);

        $D = $this->getD();
        $orders = $D->relation(true)
                    ->where($where)
                    ->order('id DESC')
                    ->limit($firstRow . ',' . $listRows)
                    ->select();

        return $orders;
    }

    protected function getM() {
        return M('Order');
    }

    protected function getD() {
        return D('Order');
    }

    protected function isRelation() {
        return true;
    }    
}
