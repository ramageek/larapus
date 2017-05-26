<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Yajra\Datatables\Html\Builder;
use Yajra\Datatables\Datatables;
use App\Book;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\File;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\BorrowLog;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\BookException;
use Excel;
use PDF;
use Validator;
use App\Author;

class BooksController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Builder $htmlBuilder)
    {
        if ($request->ajax()) {
            $books = Book::with('author');
            return Datatables::of($books)->addColumn('action',function($book){
                return view('datatable._action',[
                    'model'=>$book,
                    'form_url'=>route('books.destroy',$book->id),
                    'edit_url'=>route('books.edit',$book->id),
                    'confirm_message'=>'Yakin mau menghapus '.$book->title.'?'
                ]);
            })->make(true);
        }

        $html = $htmlBuilder
            ->addColumn(['data'=>'title','name'=>'title','title'=>'Judul'])
            ->addColumn(['data'=>'amount','name'=>'amount','title'=>'Jumlah'])
            ->addColumn(['data'=>'author.name','name'=>'author.name','title'=>'Penulis'])
            ->addColumn(['data'=>'action','name'=>'action','title'=>'','orderable'=>false,'searchable'=>false]);

        return view('books.index')->with(compact('html'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('books.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreBookRequest $request)
    {
        $book = Book::create($request->except('cover'));

        // isi field cover jika ada cover yang diupload
        if ($request->hasFile('cover')) {
            // Mengambil file yang diupload
            $uploaded_cover = $request->file('cover');

            // mengambil extension file
            $extension = $uploaded_cover->getClientOriginalExtension();

            // membuat nama file random berikut extension
            $filename = md5(time()) . '.' . $extension;

            // menyimpan cover ke folder public/img
            $destinationPath = public_path() . DIRECTORY_SEPARATOR . 'img';
            $uploaded_cover->move($destinationPath, $filename);

            // mengisi field cover di book dengan filename yang baru dibuat
            $book->cover = $filename;
            $book->save();
        }

        Session::flash("flash_notification", [
            "level"=>"success",
            "message"=>"Berhasil menyimpan $book->title"
        ]);

        return redirect()->route('books.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $book = Book::find($id);
        return view('books.edit')->with(compact('book'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateBookRequest $request, $id)
    {
        $book = Book::find($id);
        if(!$book->update($request->all())) return redirect()->back();

        if ($request->hasFile('cover')) {
            // Ambil cover serta ektensi
            $filename = null;
            $uploaded_cover = $request->file('cover');
            $extension = $uploaded_cover->getClientOriginalExtension();

            // Buat filename
            $filename = md5(time()).'.'.$extension;

            // Pindah file
            $destinationPath = public_path().DIRECTORY_SEPARATOR.'img';
            $uploaded_cover->move($destinationPath,$filename);

            // Hapus cover
            $this->hapus_cover($book->cover);

            // Penggantian cover
            $book->cover = $filename;
            $book->save();
        }

        Session::flash('flash_notification',[
            'level'=>'success',
            'message'=>'Berhasil menyimpan '.$book->title
        ]);

        return redirect()->route('books.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request=NULL,$id)
    {
        $book = Book::find($id);
        $cover = $book->cover;
        if(!$book->delete()) return redirect()->back();
        if($request->ajax()) return response()->json(['id'=>$id]);

        // Hapus cover
        $this->hapus_cover($cover);

        Session::flash('flash_notification',[
            'level'=>'success',
            'message'=>'Buku berhasil dihapus.'
        ]);

        return redirect()->route('books.index');
    }

    protected function hapus_cover($cover){
        $old_cover = $cover;
        $filepath = public_path().DIRECTORY_SEPARATOR.'img'.DIRECTORY_SEPARATOR.$cover;

        try {
            File::delete($filepath);
        } catch (FileNotFoundException $e) {
            // File terhapus
        }
    }

    public function borrow($id){
        try {
            $book = Book::findOrFail($id);
            Auth::user()->borrow($book);
            Session::flash('flash_notification',[
                'level'=>'success',
                'message'=>'Berhasil meminjam '.$book->title
            ]);
        } catch (BookException $e) {
            Session::flash('flash_notification',[
                'level'=>'danger',
                'message'=>$e->getMessage()
            ]);
        } catch (ModelNotFoundException $e) {
            Session::flash('flash_notification',[
                'level'=>'danger',
                'message'=>'Buku tidak ditemukan'
            ]);
        }
        return redirect('/');
    }

    public function returnBack($book_id){
        $borrowLog = BorrowLog::where('user_id',Auth::user()->id)->where('book_id',$book_id)->where('is_returned',0)->first();

        if($borrowLog){
            $borrowLog->is_returned = true;
            $borrowLog->save();

            Session::flash('flash_notification',[
                'level'=>'success',
                'message'=>'Berhasil mengembalikan '.$borrowLog->book->title
            ]);

            return redirect('/home');
        }
    }

    public function export(){
        return view('books.export');
    }

    public function exportPost(Request $request){
        // Validasi
        $this->validate($request,[
            'author_id'=>'required',
            'type'=>'required|in:pdf,xls'
        ],[
            'author_id.required'=>'Anda belum memilih penulis. Pilih minimal 1 penulis'
        ]);

        $books = Book::whereIn('author_id',$request->get('author_id'))->get();
        $handler = 'export'.ucfirst($request->get('type'));

        return $this->$handler($books);
    }

    public function generateExcelTemplate(){
        Excel::create('Template Import Buku',function($excel){
            // Set property
            $excel->setTitle('Template Import Buku')->setCreator('Larapus')->setCompany('Larapus')->setDescription('Template import buku Larapus');
            $excel->sheet('Data Buku',function($sheet){
                $row = 1;
                $sheet->row($row,[
                    'judul',
                    'penulis',
                    'jumlah'
                ]);
            });
        })->export('xlsx');
    }

    public function importExcel(Request $request){
        // Validasi excel
        $this->validate($request,['excel'=>'required|mimes:xls,xlsx']);
        // Ambil file
        $excel = $request->file('excel');
        // Baca sheet pertama
        $excels = Excel::selectSheetsByIndex(0)->load($excel,function($reader){
            // Options
        })->get();
        // Validasi tiap row
        $rowRules = [
            'judul'=>'required',
            'penulis'=>'required',
            'jumlah'=>'required'
        ];
        // Catat id tiap row
        $books_id = [];
        // Mulai looping row ke 2
        foreach($excels as $row){
            // Validasi tiap row dan ubah ke array
            $validator = Validator::make($row->toArray(),$rowRules);
            // Skip baris kosong
            if($validator->fails()) continue;
            // Cek penulis
            $author = Author::where('name',$row['penulis'])->first();
            // Tambah penulis baru
            if(!$author){
                $author = Author::create(['name'=>$row['penulis']]);
            }
            // Cek judul dan penulis
            $author_id = Author::where('name',$row['penulis'])->first()->id;
            $bookAuthor = Book::where('author_id',$author_id)->where('title',$row['judul'])->first();
            if (!$bookAuthor) {
                // Simpan buku
                $book = Book::create([
                    'title'=>$row['judul'],
                    'author_id'=>$author->id,
                    'amount'=>$row['jumlah']
                ]);
            }
            if(empty($book)) continue;
            // Catat id buku
            array_push($books_id, $book->id);
        }
        // Ambil buku baru
        $books = Book::whereIn('id',$books_id)->get();
        // Redirect gagal
        if ($books->count() == 0) {
            Session::flash('flash_notification',[
                'level'=>'danger',
                'message'=>'Tidak ada buku yang berhasil diimport'
            ]);
            return redirect()->back();
        }
        // Notif berhasil
        Session::flash('flash_notification',[
            'level'=>'success',
            'message'=>'Berhasil mengimport '.$books->count().' buku'
        ]);
        // Redirect ke review
        return view('books.import-review')->with(compact('books'));
    }

    private function exportXls($books){
        Excel::create('Data Buku Larapus',function($excel) use ($books){
            // Set property
            $excel->setTitle('Data Buku Larapus')->setCreator (Auth::user()->name);
            $excel->sheet('Data Buku',function($sheet) use ($books){
                $row = 1;
                $sheet->row($row,[
                    'No',
                    'Judul',
                    'Jumlah',
                    'Stok',
                    'Penulis'
                ]);
                $x = 1;
                foreach ($books as $book) {
                    $sheet->row(++$row,[
                        $x++,
                        $book->title,
                        $book->amount,
                        $book->stock,
                        $book->author->name
                    ]);
                }
            });
        })->export('xlsx');
    }

    private function exportPdf($books){
        $pdf = PDF::loadview('pdf.books',compact('books'));
        return $pdf->download('books.pdf');
    }
}
