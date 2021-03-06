<?php

namespace App\Http\Controllers;

use App\Models\Kriteria;
use App\Models\LetakBarang;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductKriteria;
use App\Models\SubCategory;
use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Http\Request;
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $all_products = Product::with('category')->with('letak')
            ->orderByName()
            ->filter(\Illuminate\Support\Facades\Request::only(['search', 'category', 'harga', 'stock']))
            ->paginate(10)
            ->withQueryString();
        return Inertia::render('Products/Index', [
            'categories' => SubCategory::orderBy('nama_kategori')
                ->get()
                ->map
                ->only('id', 'nama_kategori'),
           'filters' => \Illuminate\Support\Facades\Request::all('search', 'category', 'harga', 'stock'),
           'products' => $all_products
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => ProductCategory::with('sub_categories')->orderBy('nama_kategori')
                ->get(),
            'letak' => LetakBarang::orderBy('name')
                ->get()
                ->map
                ->only('id', 'name'),
            'suppliers' => Supplier::get()
                ->map
                ->only('id', 'nama_supplier'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'gambar' => 'required|image',
                'nama_barang' => 'required|string',
                'harga_beli' => 'required',
                'deskripsi' => 'required',
                'harga_jual' => 'required',
                'category_id' => 'required',
                'supplier_id' => 'required',
                'letak_id' => 'required',
                'kode_barang' => 'required|string|unique:products',
                'stok' => 'required',
            ]);
            QrCode::size(500)
                ->format('png')
                ->generate($request->kode_barang, public_path('/qr_codes/'.$request->kode_barang . '.png'));
            $presentase_keuntungan = (($request->harga_jual-$request->harga_beli)/$request->harga_beli)*100;
            $product = Product::create([
                'nama_barang' => $request->nama_barang,
                'harga_jual' => $request->harga_jual,
                'deskripsi' => $request->deskripsi,
                'harga_beli' => $request->harga_beli,
                'category_id' => $request->category_id,
                'supplier_id' => $request->supplier_id,
                'added_by' => Auth::user()->id,
                'kode_barang' => $request->kode_barang,
                'stok' => $request->stok,
                'letak_id' => $request->letak_id,
                'qr_code' => $request->kode_barang . '.png',
                'presentase_keuntungan' => $presentase_keuntungan,
                'gambar' => $request->file('gambar') ? $request->file('gambar')->store('products') : null,
            ]);
            $all_product_supplier = Product::where('supplier_id', $product->supplier_id)->get();
            $kriteria_id_harga = null;
            $kriteria_id_supplier = null;
            $kriteria_id_rating = null;
            $kriteria_harga = Kriteria::where('kode', 'C3')
                ->where('category_id', $request->category_id)
                ->get();
            $kriteria_supplier = Kriteria::where('kode', 'C1')->get();
            $kriteria_rating = Kriteria::where('kode', 'C2')->get();
            $random_rating = rand(1,5);
            foreach ($kriteria_harga as $k){
                if($product->harga_jual<=$k->interval_max&&$product->harga_jual>=$k->interval_min){
                    $kriteria_id_harga = $k->id;
                    break;
                }
            }
            foreach ($kriteria_supplier as $k){
                if(count($all_product_supplier)<=$k->interval_max&&count($all_product_supplier)>=$k->interval_min){
                    $kriteria_id_supplier = $k->id;
                    break;
                }
            }
            foreach ($kriteria_rating as $k){
                if($random_rating<=$k->interval_max&&$random_rating>=$k->interval_min){
                    $kriteria_id_rating = $k->id;
                    break;
                }
            }
            $kriteria_product = ProductKriteria::insert([
                [
                    'product_id' => $product->id,
                    'nilai' => count($all_product_supplier),
                    'kriteria_id' => $kriteria_id_supplier
                ],
                [
                    'product_id' => $product->id,
                    'nilai' => $random_rating,
                    'kriteria_id' => $kriteria_id_rating
                ],
                [
                    'product_id' => $product->id,
                    'nilai' => $product->harga_jual,
                    'kriteria_id' => $kriteria_id_harga
                ],
            ]);
            foreach ($all_product_supplier as $product) {
                ProductKriteria::where('product_id', $product->id)
                    ->whereHas('kriteria', function($q){
                        $q->where('kode', 'C1');
                    })->update([
                        'nilai' => count($all_product_supplier),
                        'kriteria_id' => $kriteria_id_supplier
                    ]);
            }
            return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan!');
        } catch (\Exception $e) {
            return redirect()->route('products.index')->with('error', 'Produk gagal ditambahkan! Error : ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Inertia\Response
     */
    public function edit($id)
    {
        try {
            $product = Product::where('id', $id)->with('category')->first();
            $category = ProductCategory::where('id', $product->category->category_id)->with('sub_categories')->first();
            $product->gambar = $product->gambar ? URL::route('image', ['path' => $product->gambar, 'w' => 60, 'h' => 60, 'fit' => 'crop']) : null;
            return Inertia::render('Products/Edit', [
                'product' => $product,
                'category_selected' => $category,
                'letak' => LetakBarang::orderBy('name')
                    ->get()
                    ->map
                    ->only('id', 'name'),
                'suppliers' => Supplier::get()
                    ->map
                    ->only('id', 'nama_supplier'),
                'categories' => ProductCategory::with('sub_categories')->orderBy('nama_kategori')->get()

            ]);
        } catch (\Exception $e) {
            return redirect()->route('products.index')->with('error', 'Terjadi kesalahan! Error : ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $product = Product::where('id', $id)->first();
        $request->validate([
            'nama_barang' => 'required|string',
            'harga_beli' => 'required',
            'deskripsi' => 'required',
            'harga_jual' => 'required',
            'category_id' => 'required',
            'supplier_id' => 'required',
            'letak_id' => 'required',
            'kode_barang' => ['required', 'string', Rule::unique('products')->ignore($product->id)],
            'stok' => 'required',
        ]);
        try {

            if ($request->kode_barang != $product->kode_barang) {
                $image_path = public_path("/qr_codes/" . $product->qr_code);
                if (file_exists($image_path)) {
                    File::delete($image_path);
                }
                QrCode::size(500)
                    ->format('png')
                    ->generate($request->kode_barang, public_path('/qr_codes/'.$request->kode_barang . '.png'));
                $product->update(['qr_code' => $request->kode_barang.'.png']);
            }
            $presentase_keuntungan = (($request->harga_jual-$request->harga_beli)/$request->harga_beli)*100;
            $product->update([
                'nama_barang' => $request->nama_barang,
                'harga_jual' => $request->harga_jual,
                'deskripsi' => $request->deskripsi,
                'harga_beli' => $request->harga_beli,
                'category_id' => $request->category_id,
                'letak_id' => $request->letak_id,
                'supplier_id' => $request->supplier_id,
                'added_by' => Auth::user()->id,
                'kode_barang' => $request->kode_barang,
                'presentase_keuntungan' => $presentase_keuntungan,
                'stok' => $request->stok,
            ]);
            $kriteria_harga = Kriteria::where('kode', 'C3')
                ->where('category_id', $request->category_id)
                ->get();
            $kriteria_supplier = Kriteria::where('kode', 'C1')->get();
            $all_product_supplier = Product::where('supplier_id', $product->supplier_id)->get();
            foreach ($kriteria_harga as $k){
                if($product->harga_jual<=$k->interval_max&&$product->harga_jual>=$k->interval_min){
                    $kriteria_id_harga = $k->id;
                    break;
                }
            }
            foreach ($kriteria_supplier as $k){
                if(count($all_product_supplier)<=$k->interval_max&&count($all_product_supplier)>=$k->interval_min){
                    $kriteria_id_supplier = $k->id;
                    break;
                }
            }
            $kriteria_product_harga = ProductKriteria::where('product_id', $product->id)
                ->whereHas('kriteria', function($q){
                    $q->where('kode', 'C3');
                })->update(
                    [
                        'product_id' => $product->id,
                        'nilai' => $product->harga_jual,
                        'kriteria_id' => $kriteria_id_harga
                    ]
                );
            $kriteria_product_supplier = ProductKriteria::where('product_id', $product->id)
                ->whereHas('kriteria', function($q){
                    $q->where('kode', 'C1');
                })->update([
                    'product_id' => $product->id,
                    'nilai' => count($all_product_supplier),
                    'kriteria_id' => $kriteria_id_supplier
                ]);
            if ($request->file('gambar')) {
                $storagePath = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();
                if (file_exists($storagePath.$product->gambar)) unlink($storagePath.$product->gambar);
                $product->update(['gambar' => $request->file('gambar')->store('products')]);
            }
            return redirect()->route('products.index')->with('success', 'Produk berhasil diupdate!');
        } catch (\Exception $e) {
            return redirect()->route('products.index')->with('error', 'Produk gagal diupdate! Error : ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        try {
            $product = Product::where('id', $id)->first();
            $image_path = public_path("/qr_codes/" . $product->qr_code);
            if (file_exists($image_path)) {
                File::delete($image_path);
            }
            $kategori_produk = ProductKriteria::where('product_id', $product->id)->get();
            $kategori_produk->delete();
            $storagePath = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();
            if (file_exists($storagePath.$product->gambar)) unlink($storagePath.$product->gambar);
            $supplier_id = $product->supplier_id;
            $product->delete();
            $all_product_supplier = Product::where('supplier_id', $supplier_id)->get();
            $kriteria_supplier = Kriteria::where('kode', 'C1')->get();
            foreach ($kriteria_supplier as $k){
                if(count($all_product_supplier)<=$k->interval_max&&count($all_product_supplier)>=$k->interval_min){
                    $kriteria_id_supplier = $k->id;
                    break;
                }
            }
            foreach ($all_product_supplier as $p) {
                ProductKriteria::where('product_id', $p->id)
                    ->whereHas('kriteria', function($q){
                        $q->where('kode', 'C1');
                    })->update([
                        'nilai' => count($all_product_supplier),
                        'kriteria_id' => $kriteria_id_supplier
                    ]);
            }
            return redirect()->route('products.index')->with('success', 'Produk berhasil dihapus!');
        } catch (\Exception $exception) {
            return redirect()->route('products.index')->with('error', 'Produk gagal dihapus! Error : ' . $exception->getMessage());

        }
    }

    public function index_category()
    {
        return Inertia::render('Rekomendasi/Index', [
            'categories' => ProductCategory::with('sub_categories')->get(),
            'input_bobot' => config('constants.label_bobot'),
        ]);
    }

    public function result_category(Request $request)
    {
        $input_supplier = $request->criteria_supplier; //diisi id dari kriteria
        $input_rating = $request->criteria_rating; //diisi id dari kriteria
        $input_harga = $request->criteria_harga; //diisi id dari kriteria
        $input_kategori = $request->category_id;
        $array_of_inputs = array($input_supplier,$input_rating,$input_harga);
        $input_kriteria_data = config('constants.bobot_user');
        $lingustik_data = config('constants.code_bobot');
        $linguistik_array = array();
        $used_inputs = array();
        $products = Product::with('criterias.kriteria.kriteria_fuzzy')
            ->where('category_id', $input_kategori)
            ->get();
        $array_of_ids = [];
        $i=0;
        foreach ($products as $product){
            $array_of_ids[$i] = $product->id;
            $i++;
        }
        $rentalKriteria = ProductKriteria::with('kriteria.kriteria_fuzzy')
            ->whereIn('product_id', $array_of_ids)
            ->with('product')
            ->orderBy('product_id')
            ->get();
        $matriks = array();
        $keterangan = getKeterangan($rentalKriteria);
        foreach ($products as $product){
            $array_of_criterias = array();
            foreach($product->criterias as $kriteria){
                $fuzzy_nums[0] = $kriteria->kriteria->kriteria_fuzzy->fuzzy_num_a;
                $fuzzy_nums[1] = $kriteria->kriteria->kriteria_fuzzy->fuzzy_num_b;
                $fuzzy_nums[2] = $kriteria->kriteria->kriteria_fuzzy->fuzzy_num_c;
                array_push($array_of_criterias, $fuzzy_nums);
            }

            $matriks[$product->id] = $array_of_criterias;
        }
        foreach ($array_of_inputs as $input){
            $used_inputs[] = $input_kriteria_data[$input];
            $linguistik_array[] = $lingustik_data[$input];
        }
        $matriks_ternormalisasi =matrikTernormalisasi($matriks, $keterangan); //di helpers
        $matriks_terbobot = matrikTerbobot($matriks_ternormalisasi, $used_inputs);
        $ideal_negatif = idealNegatif($matriks_terbobot);
        $ideal_positif = idealPositif($matriks_terbobot);
        $dplus = dPlus($matriks_terbobot, $ideal_positif);
        $dmin = dMin($matriks_terbobot, $ideal_negatif);
        return Inertia::render('Rekomendasi/Detail', [
            'daftar_barang' => $products,
            'bobot' => $used_inputs,
            'linguistik' => $linguistik_array,
            'keterangan' => $keterangan,
            'matriks' => $matriks,
            'matriks_ternormalisasi' => matrikTernormalisasi($matriks, $keterangan),
            'matriks_terbobot' => matrikTerbobot(matrikTernormalisasi($matriks, $keterangan), $used_inputs),
            'ideal_negatif' => idealNegatif(matrikTerbobot(matrikTernormalisasi($matriks, $keterangan), $used_inputs)),
            'ideal_positif' => idealPositif(matrikTerbobot(matrikTernormalisasi($matriks, $keterangan), $used_inputs)),
            'dplus' => $dplus,
            'dmin' => $dmin,
            'preferensi' => nilaiPreferensi($dplus, $dmin),
            'ranking' => rangking(nilaiPreferensi($dplus, $dmin))
        ]);
    }



}
