<?php

namespace frontend\controllers;

use common\helpers\Curl;
use common\models\DataPp0;
use common\models\DataSr0;
use common\models\UserPoints;
use frontend\models\Article;
use Yii;
use frontend\models\User;
use frontend\models\Product;
use frontend\models\DataAll;
use frontend\models\Retail;
use common\models\Order;
use yii\log\FileTarget;
use frontend\models\UserCharge;
use common\models\DataCl;
use console\models\GatherJincheng;
class SiteController extends \frontend\components\Controller
{
    public $url = STOCKET_URL;

    // 交易产品列表
    public $productList = [
        'sr0'=>'TJNI',
        'm0'=>'TJCU',
        'y0'=>'TJAP',
        'p0'=>'TJPD',
        'cl'    => 'TJAG',
        'pp0' =>'TJAL',
        //'a50'   => 'WGCNA0',
        //'ixic'  => 'CENQA0',
        //'bp'    => 'WICMBPA0',
        //'ec'    => 'WICMECA0',
        //'ad'    => 'WICMADA0',
        //'cd'    => 'WICMCDA0',

        //'au0' => 'SCau0001',
        //'ag0' => 'SCag0001',
        //'cu0' => 'SCcu0001',
        //'ni0' => 'SCni0001',
        //'bu0' => 'SCbu0001',
        //'ru0' => 'SCru0001',
        //'rb0' => 'SCrb0001',
        //'p0'  => 'DCp0001',
        //'sr0' => 'ZCSR0001',
        //'m0'  => 'DCm0001',
        //'y0'  => 'DCy0001',
        //'pp0' => 'DCpp0001',

        //'cl'    => 'NECLN0',
        //'gc'    => 'CMGCQ0',
        //'si'    => 'CMSIN0',
        //'hg'    => 'CMHGN0',
        //'dax'   => 'CEDAXM0',
        //'hkhsi' => 'HIHSI06',
        //'mhi'   => 'HIMHI06',
        //'a50'   => 'WGCNM0',
        //'ixic'  => 'CENQU0',
        //'bp'    => 'WICMBPM0',
        //'ec'    => 'WICMECM0',
        //'ad'    => 'WICMADA0',
        //'cd'    => 'WICMCDM0',
        //
        //'au0' => 'SCau1812',
        //'ag0' => 'SCag1812',
        //'cu0' => 'SCcu1807',
        //'ni0' => 'SCni1809',
        //'bu0' => 'SCbu1812',
        //'ru0' => 'SCru1809',
        //'rb0' => 'SCrb1810',
        //'p0'  => 'DCp1809',
        //'sr0' => 'ZCSR1809',
        //'m0'  => 'DCm1809',
        //'y0'  => 'DCy1809',
        //'pp0' => 'DCpp1809',
    ];


    public function actionRun()
    {
        $obj = new Product();
        foreach($this->productList as $k => $v){
            $params = [
                'u'      => STOCKET_USER,
                'type'   => 'stock',
                'symbol' => $v,
            ];
            $req = $this->sendRequest($this->url, $params, 'GET', []);
            if ($req['ret']) {

                $data[$k] = gzdecode($req['msg']);
                $_data = json_decode($data[$k],true);
                //$a = '[{"Date":"1546874802","Symbol":"NECLA0","Name":"美原油连续","Price3":47.96,"Vol2":1,"Open_Int":367400,"Price2":48.920624,"LastClose":48.31,"Open":48.3,"High":49.47,"Low":48.11,"NewPrice":49.26,"Volume":448074,"Amount":0,"BP1":49.25,"BP2":0,"BP3":0,"BP4":0,"BP5":0,"BV1":23,"BV2":0,"BV3":0,"BV4":0,"BV5":0,"SP1":49.26,"SP2":0,"SP3":0,"SP4":0,"SP5":0,"SV1":9,"SV2":0,"SV3":0,"SV4":0,"SV5":0,"PriceChangeRatio":1.96646}]';
                if ($_data[0]){
                    $_tmpArr = array_flip($this->productList);
                    $_data = [
                        'symbol'       => $_data[0]['Symbol'],
                        'product_name' => $_data[0]['Name'],
                        'price'        => 3*$_data[0]['NewPrice'],
                        'time'         => date('Y-m-d H:i:s'),
                        'diff'         => '',
                        'diff_rate'    => $_data[0]['PriceChangeRatio'],
                        'open'         => 3*$_data[0]['Open'],
                        'high'         => 3*$_data[0]['High'],
                        'low'          => 3*$_data[0]['Low'],
                        'close'        => 3*$_data[0]['LastClose'],
                        'bp'           => $_data[0]['BP1'],
                        'sp'           => $_data[0]['SP1'],
                        'bv'           => $_data[0]['BV1'],
                        'sv'           => $_data[0]['SV1'],
                        'date'         => $_data[0]['Date'],
                    ];
                    $os = $obj::findOne(['table_name'=>$k]);
                    if (!empty($_tmpArr[$_data['symbol']])) {

                        $_key = $_tmpArr[$_data['symbol']];
                        self::dbUpdate('data_all', $_data, ['name' => $_key]);
                    }
                    // 监听是否有人应该平仓
                    $this->listen();
                }
            }
            sleep(3);
        }
    }

    /**
     * CURL发送Request请求,含POST和REQUEST
     *
     * @param string $url     请求的链接
     * @param mixed  $params  传递的参数
     * @param string $method  请求的方法
     * @param mixed  $options CURL的参数
     *
     * @return array
     */


    public function sendRequest($url, $params = [], $method = 'POST', $options = [])
    {
        $method       = strtoupper($method);
        $protocol     = substr($url, 0, 5);
        $query_string = is_array($params) ? http_build_query($params) : $params;

        $ch       = curl_init();
        $defaults = [];
        if ('GET' == $method) {
            $geturl                = $query_string ? $url . (stripos($url,
                    "?") !== false ? "&" : "?") . $query_string : $url;
            $defaults[CURLOPT_URL] = $geturl;
            //echo $geturl;
        } else {
            $defaults[CURLOPT_URL] = $url;
            if ($method == 'POST') {
                $defaults[CURLOPT_POST] = 1;
            } else {
                $defaults[CURLOPT_CUSTOMREQUEST] = $method;
            }
            $defaults[CURLOPT_POSTFIELDS] = $query_string;
        }

        $defaults[CURLOPT_HEADER]         = "utf-8";
        $defaults[CURLOPT_USERAGENT]      = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
        $defaults[CURLOPT_FOLLOWLOCATION] = true;
        $defaults[CURLOPT_RETURNTRANSFER] = true;
        $defaults[CURLOPT_CONNECTTIMEOUT] = 10;
        $defaults[CURLOPT_TIMEOUT]        = 10;

        // disable 100-continue
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);

        if ('https' == $protocol) {
            $defaults[CURLOPT_SSL_VERIFYPEER] = false;
            $defaults[CURLOPT_SSL_VERIFYHOST] = false;
        }

        curl_setopt_array($ch, (array) $options + $defaults);

        $ret = curl_exec($ch);
        $err = curl_error($ch);

        if (false === $ret || ! empty($err)) {
            $errno = curl_errno($ch);
            $info  = curl_getinfo($ch);
            curl_close($ch);

            return [
                'ret'   => false,
                'errno' => $errno,
                'msg'   => $err,
                'info'  => $info,
            ];
        }
        curl_close($ch);



        return [
            'ret' => true,
            'msg' => $ret,
        ];
    }
    protected function listen()
    {
        //$this->afterInsert();
        // 更新所有持仓订单的浮亏
//        self::db('  UPDATE
//                        `order` o,
//                        product p,
//                        data_all a
//                    SET
//                        sell_price = a.price,
//                        profit = IF (
//                            o.rise_fall = ' . Order::RISE . ',
//                            a.price - o.price,
//                            o.price - a.price
//                        ) * o.hand * o.one_profit
//                    WHERE
//                        a.name = p.`table_name`
//                    AND o.product_id = p.id
//                    AND o.order_state =  ' . Order::ORDER_POSITION . '
//                    AND sell_price != a.price')
//        ->execute();
        // 获取所有品类当前交易状态
        $productMap = $this->getAllTradeTime();
        $extra      = [];
        foreach ($productMap as $product => $isTrade) {
            if ($isTrade === true) {
                $extra[] = $product;
            }
        }
        if ($extra) {
            $extraWhere = ' OR (order_state = ' . Order::ORDER_POSITION . ' and product_id in (' . implode(',',
                    $extra) . ') )';
        } else {
            $extraWhere = '';
        }

        // 获取所有止盈止损订单ID
        $ids = self::db('SELECT o.id, a.price, o.rise_fall, o.product_id,o.stop_profit_price,o.stop_loss_price 
FROM `order` o INNER JOIN product p on p.id = o.product_id INNER JOIN data_all a on a.name = p.table_name where 
            ( o.order_state = ' . Order::ORDER_POSITION . ')' . $extraWhere)->queryAll();
        array_walk($ids, function ($value) use ($extra) {
            //最新价格
            $price = $value['price'];
            if ($value['rise_fall'] == Order::RISE) {
                if ($price >= $value['stop_profit_price'] || $price <= $value['stop_loss_price']) {
                    Order::sellOrder($value['id'], true, $price);
                }
            } else {

                if ($price <= $value['stop_profit_price'] || $price >= $value['stop_loss_price']) {
                    Order::sellOrder($value['id'], true, $price);
                }
            }

            if (in_array($value['product_id'], $extra)) {
                Order::sellOrder($value['id'], true, $price);
            }
        });
//        $ids = self::db('SELECT id from `order` where (order_state = ' . Order::ORDER_POSITION . ' AND (
//            profit + deposit <= 0 OR (profit <= stop_loss_price * -1 AND stop_loss_point <> 0) OR (profit >= stop_profit_price AND stop_profit_point <> 0)))' . $extraWhere)->queryAll();
//        array_walk($ids, function ($value) {
//            Order::sellOrder($value['id'], true);
//        });
    }

    protected function getAllTradeTime()
    {
        $data     = [];
        $products = Product::find()->where([
            'force_sell' => Product::FORCE_SELL_YES,
            'state'      => Product::STATE_VALID,
        ])->select(['id'])->asArray()->all();
        foreach ($products as $product) {
            $data[$product['id']] = Product::isLastTradeTime($product['id'], 120);
        }

        return $data;
    }
    public function beforeAction($action)
    {
        if (! parent::beforeAction($action)) {
            return false;
        } else {
            $actions = ['login', 'register', 'forget', 'verify-code', 'kline', 'get-price','run','hynotify'];
            if (user()->isGuest && ! in_array($this->action->id, $actions)) {
                $this->redirect(['site/login']);

                return false;
            }

            return true;
        }
    }

    public function actionHynotify()
    {
        $log = new FileTarget();
        $log->logFile = Yii::getAlias('@givemoney/recharge.log');
        $status = post('status'); //状态
        $customerid = post('customerid'); //商户id
        $sdpayno = post('sdpayno');//平台订单号
        $sdorderno = post('sdorderno');  //商户订单号
        $total_fee = post('total_fee'); //到账金额
        $paytype = post('paytype');
        $sign = post('sign');
        $check_sign = md5('customerid='.$customerid.'&status='.$status.'&sdpayno='.$sdpayno.'&sdorderno='.$sdorderno.'&total_fee='.$total_fee.'&paytype='.$paytype.'&7a68ac64e17a0ba4e1e8c859f6df022399701710');
        $log->messages[] = ['订单:'.$sdorderno.'充值:'.$total_fee.'签名:'.$sign.'校验签名:'.$check_sign,8,'application',time()];
        $log->export();
        if($check_sign == $sign){
            if ($status == 1) {
                $userCharge = UserCharge::find()->where('trade_no = :trade_no', [':trade_no' => $sdorderno])->one();
                if (!empty($userCharge)) {
                    //充值状态：1待付款，2成功，-1失败
                    if ($userCharge->charge_state == 1) {
                        //找到这个用户
                        $user = User::findOne($userCharge->user_id);

                        //给用户加钱
                        $user->account += $userCharge->amount;
                        if ($user->save()) {
                            //更新充值状态---成功
                            $userCharge->charge_state = 2;
                        }
                    }
                    //更新充值记录表
                    $userCharge->update();

                    echo "success";
                    exit();
                }
            }
        }
    }

    public function actionIndex()
    {
        $this->view->title = '首页';
        //国内期货
        $cashProduct   = Product::getIndexCashProduct();
        $volumeProduct = Product::getIndexVolumeProduct();
        $productCode   = implode(',', array_values(Yii::$app->params['productCode']));

        $article = Article::find()->orderBy('publish_time desc')->one();

        return $this->render('index', compact('cashProduct', 'volumeProduct', 'productCode', 'article'));
    }

    /**
     * 获取商品价格
     * @return mixed
     */
    public function actionAjaxAllProduct()
    {
//        $name = post('data');
        //周末休市
        // if (date('w') == 6 || date('w') == 0) {
        //     return success(['name' => $name, 'price' => '休市', 'diff' => '休市', 'diff_rate' => '休市']);
        // }
        //期货的最新价格数据集
        $newData = DataAll::allProductPrice();

        if (! empty($newData)) {
//            return success($newData->attributes);
            return success($newData);
        }

        return error('无此期货数据！');
    }

    public function actionIndexDetail()
    {
        return $this->render('indexDetail');
    }

    public function actionRule()
    {
        $this->view->title = '平台介绍';

        return $this->render('rule');
    }

    // public function actionPc()
    // {
    //     $order = Order::find()->where(['order_state' => 1])->all();
    //     foreach($order as $k => $v ) {
    //         Order::sellOrder($v['id'],1);

    //     }
    //     test('运行成功');
    // }

    public function actionAttention()
    {
        $this->view->title = '我的关注';

        return $this->render('attention');
    }

    //期货的最新价格数据集
    public function actionAjaxNewProductPrice()
    {
        $name = post('data');
        //周末休市
        if ((date('w') == 6 && date('G') > 4) || date('w') == 0) {
            return success(['name' => $name, 'price' => '休市', 'diff' => '休市', 'diff_rate' => '休市']);
        }
        //期货的最新价格数据集
        $newData = DataAll::newProductPrice($name);
        if (! empty($newData)) {
            return success($newData->attributes);
        }

        return error('无此期货数据！');
    }

    public function actionRegister()
    {
        $this->view->title = '注册';

        $model                 = new User(['scenario' => 'register']);
        $model->registerMobile = session('registerMobile');

        //有微圈显示邀请码
        if (get('pid')) {
            $model->pid  = get('pid');
            $user        = User::find()->where(['id' => $model->pid])->one();
            $retail      = Retail::find()->joinWith(['adminUser'])->where(['adminUser.id' => $user->admin_id])->one();
            $model->code = isset($retail) ? $retail->code : '';
        }

        if ($model->load(post())) {
            $model->username = $model->mobile;
            $model->face     = config('web_logo');
            $userPhone       = User::find()->where(['username' => $model->username])->one();
            if (! empty($userPhone)) {
                return error('已经注册过了');
            }

            // 默认模拟账户金额
            $model->moni_acount = 100000;

            if (empty($model->code)) {
                $model->code = '344485';
            }
            if ($model->validate()) {
                $retail = Retail::find()->joinWith(['adminUser'])
                    //->andWhere(['adminUser.power' => 9998])
                    ->andWhere(['retail.code' => $model->code])
                    ->one();
                if (! empty($retail)) {
                    $model->admin_id = $retail->adminUser->id;
                } else {
                    return error('请填写正确的邀请码');
                }
                $model->face = config('web_logo');
                $model->hashPassword()->insert(false);
                $model->login(false);

                UserPoints::getPoints($model->id, UserPoints::TYPE_GET_REGISTER);

                return success(url(['site/index']));
                // return $this->goBack();
            } else {
                return error($model);
            }

//            if (!empty($model->code)) {
//                $retail = Retail::find()->joinWith(['adminUser'])->where(['code' => $model->code])->one();
//                if (!empty($retail)) {
//                    $model->pid = 0;
//                    $model->admin_id = $retail->adminUser->id;
//                    //已扫码为主
//                    if (!empty($model->pid)) {
//                        $user = User::find()->where(['state' => User::STATE_VALID, 'mobile' => $model->pid])->one();
//                        if (!empty($user)) {
//                            $model->pid = $user->id;
//                            $model->admin_id = $user->admin_id;
//                        }
//                    }
//                } else {
//                    return error('请填写正确的邀请码(如果有邀请人手机号，邀请码可以为空！)');
//                }
//            } else {
//                $user = User::find()->where(['state' => User::STATE_VALID, 'mobile' => $model->pid])->one();
//                if (!empty($user)) {
//                    $model->pid = $user->id;
//                    $model->admin_id = $user->admin_id;
//                } else {
//                    return error('请填写正确的邀请码，或者扫码进入！');
//                }
//            }
//
//            if ($model->validate()) {
//                $model->hashPassword()->insert(false);
//                $model->login(false);
//                //新用户注册
//                $model->giveRegUserCoupon();
//                return success(url(['site/index']));
//            } else {
//                return error($model);
//            }
        }
//        $user = User::findOne(get('id'));
//
//        if (!empty($user)) {
//            $model->pid = $user->mobile;
//            $model->recMobile = substr($user->mobile, 0, 3) . '*****' . substr($user->mobile, -3) . '            (推广人)';
//        }

        return $this->render('register', compact('model', 'wx'));
    }

    public function actionLogin()
    {
        self::beginForm();
        $this->view->title = config('web_name', '夕秀微盘') . '-登录';

        if (! user()->isGuest) {
            return $this->redirect(['index']);
        }

        $model = new User(['scenario' => 'login']);

        if ($model->load(post())) {
            $user = User::find()->where(['mobile' => $model->username])->one();
            if (! empty($user)) {
                if ($user->state == User::STATE_INVALID) {
                    return error('您的账号已经冻结！');
                }
            }
            if ($model->login()) {
                return success(url('site/index'));
            } else {
                return error($model);
            }
        }

        return $this->render('login', compact('model'));
    }

    public function actionShareUrl($id)
    {
        $this->view->title = '邀请注册界面';
        $url               = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . WX_APPID . '&redirect_uri=http%3a%2f%2f' . $_SERVER['HTTP_HOST'] . '/site/register%3fid%3d' . $id . '&response_type=code&scope=snsapi_userinfo&state=index#wechat_redirect';

        return $this->render('shareUrl', compact('url'));
    }

    public function actionForget()
    {
        $this->view->title = '忘记密码';
        $model             = new User(['scenario' => 'forget']);

        if ($model->load(post())) {
            $user = User::find()->andWhere(['mobile' => post('User')['mobile']])->one();
            if (! $user) {
                return error('请输入手机号码！');
            }
            if ($model->validate()) {
                $user->password = $model->password;
                $user->hashPassword()->update();
                $user->login(false);

                return success(url('site/index'));
                // return $this->goBack();
            } else {
                return error($model);
            }
        }

        return $this->render('forget', compact('model'));
    }

    /**
     * 退出登录
     * @return \yii\web\Response
     */
    public function actionLogout()
    {
        user()->logout(false);

        return $this->redirect(['index']);
    }

    /**
     * 验证码
     * @return mixed
     */
    public function actionVerifyCode()
    {
        $mobile = post('mobile');
        // 生成随机数，非正式环境一直是1234
        $randomNum = YII_ENV_PROD ? rand(1024, 9951) : 1234;
        $res       = sendsms($mobile, $randomNum);
        // $res = ['code'=>2, 'info' => $randomNum];
        if ($res['code'] == 2) {
            // 记录随机数
            session('verifyCode', $randomNum, 1800);
            session('registerMobile', $mobile);
        }

        return success($res['info']);
    }

    public function actionTest(){

       $info =  DataSr0::find()->all();
       foreach ($info as $k=>$v){
           print_r($v->Name);
       }
        $riseQuery = Order::find()->joinWith('product')->where([
            'order_state'        => 2,
            'product.table_name' => 'cl',
        ])->select('SUM(hand) hand')->one();
        dump($riseQuery->hand);
    }

     public function actionTest1(){
         $gather = new GatherJincheng();
         $gather->run();
     }

    /**
     * @param $id
     * 获取商品数据
     */
    public function actionGetData()
    {
        $symbol = get('symbol');
        $type = get('type');
        $model = Product::find()->where(['identify'=>$symbol])->one();
        $name  = $model->table_name;
        $data  = self::db("SELECT
            id,
            price,
            Low,
            High,
            Open,
            Volume,
            Amount,
            Close,
            time
        FROM
            data_{$name}
        ORDER BY
            id DESC
        LIMIT 400")->queryAll();

        $data1  = self::db("SELECT
            time
        FROM
            data_{$name}
        ORDER BY
            id DESC
        LIMIT 1")->queryOne();
        $arr = [];
        if ($type == 5){
            foreach ($data as $k=>$v){
                if (!(($v['time']-$data1['time'])%1800)){
                    $arr[] = $v;
                }
            }
            $data = $arr;

        }
        array_filter($data,function (&$item){
            $item['Date'] = (string)($item['time']);
            $item['Open'] = floatval($item['Open']);
            $item['Low'] = floatval($item['Low']);
            $item['High'] = floatval($item['High']);
            $item['Close'] = floatval($item['Close']);
            $item['Volume'] = floatval($item['Volume']);
            $item['Amount'] = floatval($item['Amount']);
            unset($item['time']);
            unset($item['id']);
        });
     echo json_encode($data);die;

        switch ($unit) {
            case 'day':
                $time   = '1';
                $format = '%Y-%m-%d';
                break;
            default:
                $lastTime = \common\models\DataAll::find()->where(['name' => $name])->one()->time;
                $time     = 'time >= "' . date('Y-m-d 00:00:00') . '"';
                $format   = '%Y-%m-%d %H:%i';
                break;
        }

        $response = Yii::$app->response;

        $response->format = \yii\web\Response::FORMAT_JSON;

        $response->data = self::db('SELECT
                sub.*, cu.price close, UNIX_TIMESTAMP(DATE_FORMAT(time, "' . $format . '")) * 1000 time
        FROM
            (
                SELECT
                    min(d1.price) low,
                    max(d1.price) high,
                    d1.price open,
                    max(d1.id) id
                FROM
                    data_' . $name . ' d1
                where ' . $time . '
                group by
                    DATE_FORMAT(time, "' . $format . '")
            ) sub,
            data_' . $name . ' cu
        WHERE
            cu.id = sub.id')->queryAll();
        $response->send();
    }

    public function actionGetFn()
    {
        $symbol = get('symbol');
        $data = DataAll::find()->where(['symbol'=>$symbol])->asArray()->one();
        $data['SP1'] = $data['sp'];
        $data['BP1'] = $data['bp'];
        $data['NewPrice'] = $data['price'];
        $data['SV1'] = rand(3,98);
        $data['BV1'] = rand(5,67);
        $data['High'] = $data['high'];
        $data['Open'] = $data['open'];
        $data['Open_Int'] = 0;
        $data['Price3'] = 0;
        $data['Low'] = $data['low'];
        $data['LastClose'] = $this->getNewClose($data['symbol']);
        $data['Price2'] = 0;
        $data['Amount'] = 0;
        if ($data['LastClose']){echo json_encode($data);}
        exit();
    }
    /**
     * 获取昨日收盘价
     * @param string $symbol 产品标识
     * @return string  昨日收盘价
     **/
    public function getNewClose($symbol){
        $k_params = [
            'market'      => $symbol,
            'type'   => '1day',
            'size'=>3
        ];
        $kline_data = $this->sendRequest(STOCKET_KURL, $k_params, 'GET', []);//k线数据
        $kline_data = $kline_data['msg'];
        $k_data = json_decode($kline_data,1);
        $yes_close = $k_data['data'][1][4];
        return $yes_close;
    }

    /**
     * 输出xml字符
     * @throws WxPayException
     **/
    private function ToXml($array)
    {
        $xml = "<xml>";
        foreach ($array as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";

        return $xml;
    }

    public function actionWrong()
    {
        $this->view->title = '错误';

        return $this->render('/user/wrong');
    }

    public function actionR1()
    {
        if (req()->isPost) {
            return success(Yii::$app->getRequest()->getHeaders());
        }

        return $this->render('r1');
    }

    public function actionR2()
    {
        if (req()->isPost) {
            return $this->redirect(['r1']);
        }

        return $this->render('r2');
    }

    public function actionDetail()
    {
        $this->layout      = false;
        $this->view->title = '图表';

        return $this->render('Detail');
    }
    public function actionKline()
    {
        $model = post('model');
        $identify = post('identify');
        switch ($model)
        {
            case '1':
                $url = "https://stock.sina.com.cn/futures/api/jsonp.php//InnerFuturesNewService.getMinLine?symbol=".$identify;
                break;
            case '5':
                $url = "http://stock2.finance.sina.com.cn/futures/api/json.php/IndexService.getInnerFuturesMiniKLine5m?symbol=".$identify;
                break;
            case '30':
                $url = "http://stock2.finance.sina.com.cn/futures/api/json.php/IndexService.getInnerFuturesMiniKLine30m?symbol=".$identify;
                break;
            case 'day':
                $url = "http://stock2.finance.sina.com.cn/futures/api/json.php/IndexService.getInnerFuturesDailyKLine?symbol=".$identify;
                break;
            default:
                $url="";
                break;
        }
        if($url)
        {
            $data = str_replace('(', '', Curl::get($url));
            $data = str_replace(');', '', $data);
            $data = json_decode($data, true);
            $data = json_encode(['data' => $data]);

            return $data;
        }
        return false;
    }

    public function actionGetLine()//分时线接口，9小时累进;全日线，当天24小时；
    {
        $id=get('pid');
        $model = Product::findModel($id);
        $name = $model->table_name;


        return false;
        if(empty(get('time')))
        {
            $end=date("Y-m-d H:i:s",time()+60*60*4);
            // $end=date("2017-12-02 09:52:44");
            $start=date("Y-m-d H:i:s",strtotime($end)-60*60*7);
        }
        else
        {
            $start=date("Y-m-d H:i:s",get('time')/1000);
            $end=date("Y-m-d H:i:s",get('time')/1000+10800);
        }
        if(get('isAllDay')=='true')
        {
            $end=date("Y-m-d 23:23:59");
            //$end=date("2017-12-02 23:23:59");
            $start=date("Y-m-d H:i:s",strtotime($end)-60*60*24);

        }

        $format='%Y-%m-%d %H:%i';
        $data = self::db("SELECT
                 cu.price indices, UNIX_TIMESTAMP(DATE_FORMAT(time,'".$format."')) * 1000 time
        FROM
            (
                SELECT
                    
                    max(d1.id) id
                FROM
                    data_" . $name . " d1
                where time >'".$start."' and time <'".$end."'
                group by
                    DATE_FORMAT(time,'".$format."')
            ) sub,
            data_" . $name . " cu
        WHERE
            cu.id = sub.id")->queryAll();
        //$response->send();
        $da=null;
        if(!empty($data))
        {
            for($i=0;$i<count($data);$i++)
            {
                $da[$i]['time']=(float)$data[$i]['time'];
                $da[$i]['indices']=(float)$data[$i]['indices'];

            }
        }

        $jsonarr['msg']="请求成功！";
        $jsonarr['success']=true;
        $jsonarr['totalCount']=0;
        $jsonarr['resultObject']['startTime']=strtotime($start)*1000;
        $jsonarr['resultObject']['endTime']=strtotime($end)*1000;
        $jsonarr['resultList']=$da;
        echo json_encode($jsonarr);

    }
    public function actionGetPrice()
    {
        $simple_identify = post('simple_identify');
        $url = "http://hq.sinajs.cn/list=hf_".$simple_identify;
        if($url)
        {
            $data = str_replace('var hq_str_hf_'.$simple_identify.'="', '', Curl::get($url));
            $data = str_replace('";', '', $data);
            $data = explode(',', $data);
            unset($data[13]);
            $data = json_encode(['data' => $data]);
            return $data;
        }
    }

    public function actionGetProList($symbol)
    {

         $symbol = explode(',',$symbol);
         $data = DataAll::find()->where(['symbol'=>$symbol])->asArray()->all();

         foreach ($data as $k=>&$v){
             $v['NewPrice'] = $v['price'];
             $v['LastClose'] = $last_close = $this->getNewClose($v['symbol']);
             $v['Symbol'] = $v['symbol'];
         }
         if ($last_close){
             echo json_encode($data);
             exit();
         }
    }
    public function actionTest2(){
        $model = new GatherJincheng();
        $model->run();
    }
}
