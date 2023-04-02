<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Admin;
use App\Login;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    //đăng kí admin
    public function register(Request $request)
    {
        $this->validation($request);
        $data = $request->all();

        $admin = new Admin();
        $admin->admin_name = $data['admin_name'];
        $admin->admin_phone = $data['admin_phone'];
        $admin->admin_email = $data['admin_email'];
        $admin->admin_password = md5($data['admin_password']);
        $admin->save();
        return redirect('/register-auth')->with('message', 'Đăng ký thành công');
    }
    public function register_auth()
    {
        return view('admin.custom_auth.register');
    }

    //đăng nhập admin
    public function login(Request $request)
    {
        $this->validate($request, [
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|max:255'
        ]);
        $login = Login::where('admin_email', $request->admin_email)->where('admin_password', md5($request->admin_password))->first();
        if ($login) {
            $login_count = $login->count();
            if ($login_count > 0) {
                Session()->put('admin_name', $login->admin_name);
                Session()->put('admin_id', $login->admin_id);
                return Redirect()->to('/dashboard');
            }
        } else {
            Session()->put('message', 'Mật khẩu hoặc tài khoản bị sai.Làm ơn nhập lại');
            return Redirect()->to('/admin');
        }
    }
    public function login_auth()
    {
        return view('admin.custom_auth.login_auth');
    }

    //đăng xuất admin
    public function logout_auth()
    {
        Auth::logout();
        return redirect('/admin')->with('message', 'Đã đăng xuất');
    }

    public function validation($request)
    {
        return $this->validate($request, [
            'admin_name' => 'required|max:255',
            'admin_phone' => 'required|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|max:255',
        ]);
    }
}
