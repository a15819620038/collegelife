<?php
namespace Home\Controller;

/**
 * 
 */
class LogsController extends CommonController {
    /**
     * 日志管理
     * @return
     */
    public function index(){
        $result = $this->pagination('Visitor');

        $this->assign('page', $result['show']);
        $this->assign('visitors', $result['data']);
        $this->display();
    }
}
