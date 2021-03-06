<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function all_order(Request $request){
        try {
            $all_orders = Order::with('products')
                ->where('user_id', Auth::id())
                ->get();
            $data = array(
                'success' => true,
                'message' => 'Berhasil menampilkan data order',
                'data' => $all_orders,
            );
            return response()->json($data, 200);
        } catch (\Exception $exception) {
            $data = array(
                [
                    'success' => false,
                    'message' => 'Terjadi kesalahan : '.$exception->getMessage()
                ]
            );
            return response()->json($data, 400);
        }
    }
    public function order_by_id($id){
        try {
            $order = Order::with('products')
                ->where('id', $id)
                ->where('user_id', Auth::id())
                ->first();
            $data = array(
                'success' => true,
                'message' => 'Berhasil menampilkan data order',
                'data' => $order,
            );
            return response()->json($data, 200);
        } catch (\Exception $exception) {
            $data = array(
                [
                    'success' => false,
                    'message' => 'Terjadi kesalahan : '.$exception->getMessage()
                ]
            );
            return response()->json($data, 400);
        }
    }
    public function pay_order($id){
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();
            $user_data = User::where('id', Auth::id())->with('saldo')->first();
            if($order) {
                if($user_data->saldo!=null){
                    if($order->total<=$user_data->saldo->jumlah){
                        $saldo = Credit::where('id', $user_data->saldo->id)->first();
                        $saldo->update(['jumlah' => $saldo->jumlah-$order->total]);
                        $order->update(['status' => 1]);
                        $data = array(
                            'success' => true,
                            'message' => 'Berhasil membayar pesanan',
                            'data' => $order,
                        );
                    } else {
                        $data = array(
                            'success' => false,
                            'message' => 'Oops! saldo Anda tidak cukup!',
                            'data' => $order,
                        );
                    }
                } else {
                    $data = array(
                        'success' => false,
                        'message' => 'Anda harus mengisi saldo terlebih dahulu untuk membeli',
                        'data' => $order,
                    );
                }

            }

            return response()->json($data, 200);
        } catch (\Exception $exception) {
            $data = array(
                [
                    'success' => false,
                    'message' => 'Terjadi kesalahan : '.$exception->getMessage()
                ]
            );
            return response()->json($data, 400);
        }
    }

    public function create_order(Request $request){
        $validator = Validator::make($request->all(), [
            'products' => 'required'
        ]);
        if ($validator->fails()) {
            $data = array(
                'success' => false,
                'message' => 'Mohon masukkan data dengan lengkap',
            );
            return response()->json($data, 400);
        } else {
            try {
                $products = json_decode(json_encode($request->products));
                $order = Order::create([
                    'user_id' => Auth::id(),
                    'status' => 0,
                ]);
                $total_semua_products = 0;
                $total = 0;
                foreach ($products as $product){
                    $total = $product->jumlah * $product->harga;
                    $total_semua_products+=$total;
                    $order->products()->attach($product->id, [
                        'jumlah' => $product->jumlah,
                        'total' => $total,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()]);
                }
                $order->update(['total' => $total_semua_products]);
                $order_data = Order::where('id', $order->id)->with('products')->first();
                $data = array(
                    'success' => true,
                    'message' => 'Berhasil menambahkan data order',
                    'data' => $order_data,
                );
                return response()->json($data,200);
            } catch (\Exception $exception) {
                $data = array(
                    [
                        'success' => false,
                        'message' => 'Terjadi kesalahan : '.$exception->getMessage()
                    ]
                );
                return response()->json($data, 400);
            }
        }
    }
}
