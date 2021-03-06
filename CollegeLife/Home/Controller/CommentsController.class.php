<?php
namespace Home\Controller;

/**
 * CommentsController
 */
class CommentsController extends CommonController {
    /**
     * RESTful create
     * @return
     */
    public function create() {
        // 未登陆不给于评论
        if (!$this->hasLogin()) {
            $this->returnJson('', 0, '请先登陆后再评论！');
        }

        $this->unvalidFormReq();

        if (!isset($_POST['goods_id'])
            || !is_numeric($_POST['goods_id'])
            || !isset($_POST['comment_content'])) {
            $this->returnJson('', 0, '无效的操作！');
        }

        // 购买过的用户才能评论
        $userService = D('User', 'Service');
        $bought = $userService->userHasBought($_SESSION['uid']);
        if (!in_array($_POST['goods_id'], $bought)) {
            $this->returnJson('', 0, '请购买完成后再评论！');
        }

        $Comment = D('Comment');
        $comment['goods_id'] = $_POST['goods_id'];
        $comment['content'] = $_POST['comment_content'];
        $comment = $Comment->create($comment);
        if (!$Comment->add($comment)) {
            $this->returnJson('', 0, '对不起，系统出错！');
        }

        // 无效token
        $this->destroyToken();
        $comment['content'] = strip_sql_injection($comment['content']);
        $this->returnJson($comment, 1, '发表评论成功');
    }

    /**
     * 返回json
     * @param  fixed $data   
     * @param  fixed $status 
     * @param  fixed $info   
     * @return array         
     */
    private function returnJson($data, $status, $info) {
        $json['data'] = $data;
        $json['status'] = $status;
        $json['info'] = $info;

        $this->ajaxReturn($json);
    }
}
