<?php
namespace Home\Controller;

/**
 * 
 */
class AdminsController extends CommonController {
    /**
     * 权限过滤
     * @return
     */
    public function _initialize() {
        parent::_initialize();

        if ($_SESSION['rank'] != 3) {
            $this->error('您没有权限查看该页！');
        }
    }

    /**
     * 管理员
     * @return 
     */
    public function index(){
        $result = $this->pagination('Admin');

        $this->assign('page', $result['show']);
        $this->assign('admins', $result['data']);
        $this->display();
    }

    /**
     * 管理员信息
     * @return
     */
    public function show() {
        if (!isset($_GET['admin_id'])) {
            $this->error('您查看的管理员不存在！');
        }

        $adminService = D('Admin', 'Service');
        $admin = $adminService->getAdminDetail($_GET['admin_id']);
        if (empty($admin)) {
            $this->error('您查看的管理员不存在！');
        }

        $this->assign('admin', $admin);
        $this->assign('orders', $admin['orders']);
        $this->display();
    }

    /**
     * 添加管理员
     * @return
     */
    public function create() {
        if (!isset($_POST['admin'])) {
            $this->error('无效的操作！');
        }

        $Admin = D('Admin');
        $admin = $_POST['admin'];
        $admin['buildings'] = implode(',', $admin['buildings']);
        if ($admin = $Admin->create($admin)) {
            if (false === $Admin->add($admin)) {
                $this->error('系统出错了！');
            } else {
                $this->redirect('Admins/index');
            }
        } else {
            $this->error(formatArrToStr($Admin->getError()));
        }
    }

    /**
     * 管理员更新
     * @return
     */
    public function update() {
        if (!isset($_GET['admin_id'])
            || !isset($_GET['operation'])) {
            $this->error('无效的操作！');
        }

        $adminService = D('Admin', 'Service');
        if ($_GET['operation'] == 'deactive') {
            if (false == $adminService->deactive($_GET['admin_id'])) {
                $this->error('系统出错了！');
            }
        } else if ($_GET['operation'] == 'active') {
            if (false == $adminService->active($_GET['admin_id'])) {
                $this->error('系统出错了！');
            }
        } else {
            $this->error('系统出错了！');
        }

        $this->redirect('Admins/index', array('p' => $_GET['p']));
    }
}