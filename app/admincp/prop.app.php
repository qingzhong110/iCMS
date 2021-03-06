<?php
/**
* iCMS - i Content Management System
* Copyright (c) 2007-2012 idreamsoft.com iiimon Inc. All rights reserved.
*
* @author coolmoo <idreamsoft@qq.com>
* @site http://www.idreamsoft.com
* @licence http://www.idreamsoft.com/license.php
* @version 6.0.0
* @$Id: prop.app.php 2369 2014-03-13 16:16:29Z coolmoo $
*/
class propApp{
    function __construct() {
        $this->pid         = (int)$_GET['pid'];
    }
    function do_add(){
        $this->categoryApp = iPHP::app('admincp.category.app','all');
        $this->category    = $this->categoryApp->category;
        $this->pid && $rs = iDB::row("SELECT * FROM `#iCMS@__prop` WHERE `pid`='$this->pid' LIMIT 1;",ARRAY_A);
        if($_GET['act']=="copy"){
            $this->pid = 0;
            $rs['val'] = '';
        }
        if(empty($rs)){
            $_GET['type'] && $rs['type']  = iS::escapeStr($_GET['type']);
            $_GET['field']&& $rs['field'] = iS::escapeStr($_GET['field']);
        }
        include iACP::view("prop.add");
    }
    function do_save(){
        $pid      = (int)$_POST['pid'];
        $cid      = (int)$_POST['cid'];
        $ordernum = (int)$_POST['ordernum'];
        $field    = iS::escapeStr($_POST['field']);
        $name     = iS::escapeStr($_POST['name']);
        $type     = iS::escapeStr($_POST['type']);
        $val      = iS::escapeStr($_POST['val']);

		($field=='pid'&& !is_numeric($val)) && iPHP::alert('pid字段的值只能用数字');
        $field OR iPHP::alert('属性字段不能为空!');
        $name OR iPHP::alert('属性名称不能为空!');
        $type OR iPHP::alert('类型不能为空!');

		$field=='pid' && $val=(int)$val;

        $fields = array('rootid','cid','field','type','ordernum', 'name', 'val');
        $data   = compact ($fields);

		if($pid){
            iDB::update('prop', $data, array('pid'=>$pid));
			$msg="属性更新完成!";
		}else{
	        iDB::value("SELECT `pid` FROM `#iCMS@__prop` where `type` ='$type' AND `val` ='$val' AND `field` ='$field' AND `cid` ='$cid'") && iPHP::alert('该类型属性值已经存在!请另选一个');
            iDB::insert('prop',$data);
	        $msg="新属性添加完成!";
		}
		$this->cache();
        iPHP::success($msg,'url:'.APP_URI);
    }
    function do_update(){
    	foreach((array)$_POST['pid'] as $tk=>$pid){
            iDB::query("update `#iCMS@__prop` set `type` = '".$_POST['type'][$tk]."', `name` = '".$_POST['name'][$tk]."', `value` = '".$_POST['value'][$tk]."' where `pid` = '$pid';");
    	}
    	$this->cache();
    	iPHP::alert('更新完成');
    }
    function do_del($id = null,$dialog=true){
    	$id===null && $id=$this->pid;
    	$id OR iPHP::alert('请选择要删除的属性!');
		iDB::query("DELETE FROM `#iCMS@__prop` WHERE `pid` = '$id';");
    	$this->cache();
    	$dialog && iPHP::success("已经删除!",'url:'.APP_URI);
    }
    function do_batch(){
        $idArray = (array)$_POST['id'];
        $idArray OR iPHP::alert("请选择要操作的属性");
        $ids     = implode(',',$idArray);
        $batch   = $_POST['batch'];
    	switch($batch){
    		case 'dels':
				iPHP::$break	= false;
	    		foreach($idArray AS $id){
	    			$this->do_del($id,false);
	    		}
	    		iPHP::$break	= true;
				iPHP::success('属性全部删除完成!','js:1');
    		break;
    		case 'refresh':
    			$this->cache();
    			iPHP::success('属性缓存全部更新完成!','js:1');
    		break;
		}
	}

    function do_iCMS(){
        $this->categoryApp = iPHP::app('admincp.category.app','all');
        $this->category    = $this->categoryApp->category;
        $sql			= " where 1=1";
//        $cid			= (int)$_GET['cid'];
//
//        if($cid) {
//	        $cids	= $_GET['sub']?iCMS::get_category_ids($cid,true):$cid;
//	        $cids OR $cids	= $vars['cid'];
//	        $sql.= iPHP::where($cids,'cid');
//        }

        $_GET['field']&& $sql.=" AND `field`='".$_GET['field']."'";
        $_GET['field']&& $uri.='&field='.$_GET['field'];

        $_GET['type'] && $sql.=" AND `type`='".$_GET['type']."'";
        $_GET['type'] && $uri.='&type='.$_GET['type'];

        $_GET['cid']  && $sql.=" AND `cid`='".$_GET['cid']."'";
        $_GET['cid']  && $uri.='&cid='.$_GET['cid'];

        $maxperpage = $_GET['perpage']>0?(int)$_GET['perpage']:20;
        $total		= iPHP::total(false,"SELECT count(*) FROM `#iCMS@__prop` {$sql}","G");
        iPHP::pagenav($total,$maxperpage,"个属性");
        $rs     = iDB::all("SELECT * FROM `#iCMS@__prop` {$sql} order by pid DESC LIMIT ".iPHP::$offset." , {$maxperpage}");
        $_count = count($rs);
    	include iACP::view("prop.manage");
    }
    function do_cache(){
        $this->cache();
        iPHP::success('缓存更新完成!','js:1');
    }
    function cache(){
    	$rs	= iDB::all("SELECT * FROM `#iCMS@__prop`");
    	foreach((array)$rs AS $row) {
            $type_field_id[$row['type'].'/'.$row['field']][$row['pid']] =
            $type_field_val[$row['type']][$row['field']][$row['val']]   = $row;
    	}
        // prop/article/author
        foreach((array)$type_field_id AS $key=>$a){
            iCache::set('iCMS/prop/'.$key,$a,0);
        }
        // prop/article
    	foreach((array)$type_field_val AS $k=>$a){
    		iCache::set('iCMS/prop/'.$k,$a,0);
    	}
    }
    function btn_group($field, $type = null,$target = null){
        $type OR $type = iACP::$app_name;
        $propArray = iCache::get("iCMS/prop/{$type}/{$field}");
        $target OR $target = $field;
        echo '<div class="btn-group">'.
        '<a class="btn dropdown-toggle iCMS-default" data-toggle="dropdown" tabindex="-1"> <span class="caret"></span> 选择</a>'.
        '<ul class="dropdown-menu">';
        foreach ((array)$propArray as $prop) {
            echo '<li><a href="javascript:;" data-toggle="insert" data-target="#' . $target . '" data-value="' . $prop['val'] . '">' . $prop['name'] . '</a></li>';
        }
        echo '<li><a class="btn" href="'.__ADMINCP__.'=prop&do=add&type='.$type.'&field='.$field.'" target="_blank">添加常用属性</a></li>';
        echo '</ul></div>';
    }
    function get_prop($field, $val = NULL,/*$default=array(),*/$out = 'option', $url="",$type = "") {
        $type OR $type = iACP::$app_name;
        $propArray = iCache::get("iCMS/prop/{$type}/{$field}");
        $valArray  = explode(',', $val);
        $opt = array();
        foreach ((array)$propArray AS $k => $P) {
            if ($out == 'option') {
                $opt[]="<option value='{$P['val']}'" . (array_search($P['val'],$valArray)!==FALSE ? " selected='selected'" : '') . ">{$P['name']}[{$field}='{$P['val']}'] </option>";
            } elseif ($out == 'array') {
                $opt[$P['val']] = $P['name'];
            } elseif ($out == 'text') {
                if (array_search($P['val'],$valArray)!==FALSE) {
                    $flag = '<i class="fa fa-flag"></i> '.$P['name'];
                    $opt[]= ($url?'<a href="'.str_replace('{PID}',$P['val'],$url).'">'.$flag.'</a>':$flag).'<br />';
                }
            }
        }
        if($out == 'array'){
            return $opt;
        }
        // $opt.='</select>';
        return implode('', $opt);
    }

}
