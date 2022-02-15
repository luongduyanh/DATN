<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Gloudemans\Shoppingcart\Facades\Cart;
use Carbon\Carbon;
use App\Http\Requests;
use Illuminate\Support\Facades\Redirect;

session_start();

use App\City;
use App\Province;
use App\Customer;
use App\Wards;
use App\Feeship;
use App\Slider;
use App\Shipping;
use App\CatePost;
use App\Order;
use App\Coupon;
use App\OrderDetails;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{

  public function confirm_order(Request $request)
  {
    $data = $request->all();
    //get coupon
    if ($data['order_coupon'] != 'no') {
      $coupon = Coupon::where('coupon_code', $data['order_coupon'])->first();
      $coupon->coupon_used = $coupon->coupon_used . ',' . Session()->get('customer_id');
      $coupon->coupon_time = $coupon->coupon_time - 1;
      $coupon_mail = $coupon->coupon_code;
      $coupon->save();
    } else {
      $coupon_mail = 'không có sử dụng';
    }
    //get van chuyen
    $shipping = new Shipping();
    $shipping->shipping_name = $data['shipping_name'];
    $shipping->shipping_email = $data['shipping_email'];
    $shipping->shipping_phone = $data['shipping_phone'];
    $shipping->shipping_address = $data['shipping_address'];
    $shipping->shipping_notes = $data['shipping_notes'];
    $shipping->shipping_method = $data['shipping_method'];
    $shipping->save();
    $shipping_id = $shipping->shipping_id;

    $checkout_code = substr(md5(microtime()), rand(0, 26), 5);

    //get order
    $order = new Order();
    $order->customer_id = Session()->get('customer_id');
    $order->shipping_id = $shipping_id;
    $order->order_status = 1;
    $order->order_code = $checkout_code;
    $order->order_destroy = "0";

    date_default_timezone_set('Asia/Ho_Chi_Minh');

    $today = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');

    $order_date = Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d');;
    $order->created_at = $today;
    $order->order_date = $order_date;
    $order->save();


    if (Session()->get('cart') == true) {
      foreach (Session()->get('cart') as $key => $cart) {
        $order_details = new OrderDetails;
        $order_details->order_code = $checkout_code;
        $order_details->product_id = $cart['product_id'];
        $order_details->product_name = $cart['product_name'];
        $order_details->product_price = $cart['product_price'];
        $order_details->product_sales_quantity = $cart['product_qty'];
        $order_details->product_coupon =  $data['order_coupon'];
        $order_details->product_feeship = $data['order_fee'];
        $order_details->save();
      }
    }



    //send mail confirm
    $now = Carbon::now('Asia/Ho_Chi_Minh')->format('d-m-Y H:i:s');

    $title_mail = "Đơn hàng xác nhận ngày" . ' ' . $now;

    $customer = Customer::find(Session()->get('customer_id'));

    $data['email'][] = $customer->customer_email;
    //lay gio hang
    if (Session()->get('cart') == true) {
      foreach (Session()->get('cart') as $key => $cart_mail) {
        $cart_array[] = array(
          'product_name' => $cart_mail['product_name'],
          'product_price' => $cart_mail['product_price'],
          'product_qty' => $cart_mail['product_qty']
        );
      }
    }
    //lay shipping
    if (Session()->get('fee') == true) {
      $fee = Session()->get('fee') . 'k';
    } else {
      $fee = '25k';
    }

    $shipping_array = array(
      'fee' =>  $fee,
      'customer_name' => $customer->customer_name,
      'shipping_name' => $data['shipping_name'],
      'shipping_email' => $data['shipping_email'],
      'shipping_phone' => $data['shipping_phone'],
      'shipping_address' => $data['shipping_address'],
      'shipping_notes' => $data['shipping_notes'],
      'shipping_method' => $data['shipping_method']

    );
    //lay ma giam gia, lay coupon code
    $ordercode_mail = array(
      'coupon_code' => $coupon_mail,
      'order_code' => $checkout_code,
    );

    Mail::send('pages.mail.mail_order',  ['cart_array' => $cart_array, 'shipping_array' => $shipping_array, 'code' => $ordercode_mail], function ($message) use ($title_mail, $data) {
      $message->to($data['email'])->subject($title_mail); //send this mail with subject
      $message->from($data['email'], $title_mail); //send from this mail
    });

    Session()->forget('coupon');
    Session()->forget('fee');
    Session()->forget('cart');
  }
  public function del_fee()
  {
    Session()->forget('fee');
    return redirect()->back();
  }

  public function AuthLogin()
  {
    $admin_id = Session()->get('admin_id');
    if ($admin_id) {
      return Redirect::to('dashboard');
    } else {
      return Redirect::to('admin')->send();
    }
  }
  public function calculate_fee(Request $request)
  {
    $data = $request->all();
    if ($data['matp']) {
      $feeship = Feeship::where('fee_matp', $data['matp'])->where('fee_maqh', $data['maqh'])->where('fee_xaid', $data['xaid'])->get();
      if ($feeship) {
        $count_feeship = $feeship->count();
        if ($count_feeship > 0) {
          foreach ($feeship as $key => $fee) {
            Session()->put('fee', $fee->fee_feeship);
            Session()->save();
          }
        } else {
          Session()->put('fee', 25000);
          Session()->save();
        }
      }
    }
  }
  public function select_delivery_home(Request $request)
  {
    $data = $request->all();
    if ($data['action']) {
      $output = '';
      if ($data['action'] == "city") {
        $select_province = Province::where('matp', $data['ma_id'])->orderby('maqh', 'ASC')->get();
        $output .= '<option>---Chọn quận huyện---</option>';
        foreach ($select_province as $key => $province) {
          $output .= '<option value="' . $province->maqh . '">' . $province->name_quanhuyen . '</option>';
        }
      } else {

        $select_wards = Wards::where('maqh', $data['ma_id'])->orderby('xaid', 'ASC')->get();
        $output .= '<option>---Chọn xã phường---</option>';
        foreach ($select_wards as $key => $ward) {
          $output .= '<option value="' . $ward->xaid . '">' . $ward->name_xaphuong . '</option>';
        }
      }
      echo $output;
    }
  }
  public function view_order($orderId)
  {
    $this->AuthLogin();
    $order_by_id = DB::table('tbl_order')
      ->join('tbl_customers', 'tbl_order.customer_id', '=', 'tbl_customers.customer_id')
      ->join('tbl_shipping', 'tbl_order.shipping_id', '=', 'tbl_shipping.shipping_id')
      ->join('tbl_order_details', 'tbl_order.order_id', '=', 'tbl_order_details.order_id')
      ->select('tbl_order.*', 'tbl_customers.*', 'tbl_shipping.*', 'tbl_order_details.*')->first();

    $manager_order_by_id  = view('admin.view_order')->with('order_by_id', $order_by_id);
    return view('admin_layout')->with('admin.view_order', $manager_order_by_id);
  }
  public function login_checkout(Request $request)
  {
    //category post
    $category_post = CatePost::orderBy('cate_post_id', 'DESC')->get();
    //slide
    $slider = Slider::orderBy('slider_id', 'DESC')->where('slider_status', '1')->take(4)->get();

    //seo 
    $meta_desc = "Đăng nhập thanh toán";
    $meta_keywords = "Đăng nhập thanh toán";
    $meta_title = "Đăng nhập thanh toán";
    $url_canonical = $request->url();
    //--seo 

    $cate_product = DB::table('tbl_category_product')->where('category_status', '0')->orderby('category_id', 'desc')->get();
    $brand_product = DB::table('tbl_brand')->where('brand_status', '0')->orderby('brand_id', 'desc')->get();

    return view('pages.checkout.login_checkout')->with('category', $cate_product)->with('brand', $brand_product)->with('meta_desc', $meta_desc)->with('meta_keywords', $meta_keywords)->with('meta_title', $meta_title)->with('url_canonical', $url_canonical)->with('slider', $slider)->with('category_post', $category_post);
  }
  public function add_customer(Request $request)
  {

    $data = array();
    $data['customer_name'] = $request->customer_name;
    $data['customer_phone'] = $request->customer_phone;
    $data['customer_email'] = $request->customer_email;
    $data['customer_token'] = "0";
    $data['customer_password'] = md5($request->customer_password);

    $customer_id = DB::table('tbl_customers')->insertGetId($data);

    Session()->put('customer_id', $customer_id);
    Session()->put('customer_name', $request->customer_name);
    return Redirect::to('/checkout');
  }
  public function checkout(Request $request)
  {
    //category post
    $category_post = CatePost::orderBy('cate_post_id', 'DESC')->get();
    //seo 
    //slide
    $slider = Slider::orderBy('slider_id', 'DESC')->where('slider_status', '1')->take(4)->get();

    $meta_desc = "Đăng nhập thanh toán";
    $meta_keywords = "Đăng nhập thanh toán";
    $meta_title = "Đăng nhập thanh toán";
    $url_canonical = $request->url();
    //--seo 

    $cate_product = DB::table('tbl_category_product')->where('category_status', '0')->orderby('category_id', 'desc')->get();
    $brand_product = DB::table('tbl_brand')->where('brand_status', '0')->orderby('brand_id', 'desc')->get();
    $city = City::orderby('matp', 'ASC')->get();

    return view('pages.checkout.show_checkout')->with('category', $cate_product)->with('brand', $brand_product)->with('meta_desc', $meta_desc)->with('meta_keywords', $meta_keywords)->with('meta_title', $meta_title)->with('url_canonical', $url_canonical)->with('city', $city)->with('slider', $slider)->with('category_post', $category_post);
  }
  public function save_checkout_customer(Request $request)
  {
    $data = array();
    $data['shipping_name'] = $request->shipping_name;
    $data['shipping_phone'] = $request->shipping_phone;
    $data['shipping_email'] = $request->shipping_email;
    $data['shipping_notes'] = $request->shipping_notes;
    $data['shipping_address'] = $request->shipping_address;

    $shipping_id = DB::table('tbl_shipping')->insertGetId($data);

    Session()->put('shipping_id', $shipping_id);

    return Redirect::to('/payment');
  }

  public function order_place(Request $request)
  {
    //insert payment_method
    //seo 
    $meta_desc = "Đăng nhập thanh toán";
    $meta_keywords = "Đăng nhập thanh toán";
    $meta_title = "Đăng nhập thanh toán";
    $url_canonical = $request->url();
    //--seo 
    $data = array();
    $data['payment_method'] = $request->payment_option;
    $data['payment_status'] = 'Đang chờ xử lý';
    $payment_id = DB::table('tbl_payment')->insertGetId($data);

    //insert order
    $order_data = array();
    $order_data['customer_id'] = Session()->get('customer_id');
    $order_data['shipping_id'] = Session()->get('shipping_id');
    $order_data['payment_id'] = $payment_id;
    $order_data['order_destroy'] = null;
    $order_data['order_total'] = Cart::total();
    $order_data['order_status'] = 1;
    $order_id = DB::table('tbl_order')->insertGetId($order_data);

    //insert order_details
    $content = Cart::content();
    foreach ($content as $v_content) {
      $order_d_data['order_id'] = $order_id;
      $order_d_data['product_id'] = $v_content->id;
      $order_d_data['product_name'] = $v_content->name;
      $order_d_data['product_price'] = $v_content->price;
      $order_d_data['product_sales_quantity'] = $v_content->qty;
      DB::table('tbl_order_details')->insert($order_d_data);
    }
    if ($data['payment_method'] == 1) {

      echo 'Thanh toán thẻ ATM';
    } elseif ($data['payment_method'] == 2) {
      Cart::destroy();
      $cate_product = DB::table('tbl_category_product')->where('category_status', '0')->orderby('category_id', 'desc')->get();
      $brand_product = DB::table('tbl_brand')->where('brand_status', '0')->orderby('brand_id', 'desc')->get();
      return view('pages.checkout.handcash')->with('category', $cate_product)->with('brand', $brand_product)->with('meta_desc', $meta_desc)->with('meta_keywords', $meta_keywords)->with('meta_title', $meta_title)->with('url_canonical', $url_canonical);
    } else {
      echo 'Thẻ ghi nợ';
    }
  }

  public function logout_checkout()
  {
    Session()->forget('customer_id');
    Session()->forget('coupon');
    Cart::destroy();

    return Redirect::to('/dang-nhap');
  }

  public function login_customer(Request $request)
  {

    $email = $request->email_account;
    $password = md5($request->password_account);
    $result = DB::table('tbl_customers')->where('customer_email', $email)->where('customer_password', $password)->first();
    if (Session()->get('coupon') == true) {
      Session()->forget('coupon');
    }

    if ($result) {
      Session()->put('customer_id', $result->customer_id);
      Session()->put('customer_name', $result->customer_name);
      return Redirect::to('/checkout');
    } else {
      return Redirect::to('/dang-nhap');
    }
    Session()->save();
  }

  public function manage_order()
  {
    $this->AuthLogin();
    $all_order = DB::table('tbl_order')
      ->join('tbl_customers', 'tbl_order.customer_id', '=', 'tbl_customers.customer_id')
      ->select('tbl_order.*', 'tbl_customers.customer_name')
      ->orderby('tbl_order.order_id', 'desc')->get();
    $manager_order  = view('admin.manage_order')->with('all_order', $all_order);
    return view('admin_layout')->with('admin.manage_order', $manager_order);
  }
}
