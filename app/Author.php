<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class Author extends Model
{
    protected $fillable = ['name'];

    public static function boot(){
      parent::boot();

      self::deleting(function($author){
        // Cek buku penulis
        if ($author->books->count() > 0) {
          // Pesan error
          $html = 'Penulis tidak bisa dihapus karena masih memiliki buku : ';
          $html .= '<ul>';
          foreach ($author->books as $book) {
            $html .= '<li>'.$book->title.'</li>';
          }
          $html .= '</ul>';
          Session::flash('flash_notification',[
            'level'=>'danger',
            'message'=>$html
          ]);
          // Batalkan penghapusan
          return false;
        }
      });
    }

    public function books(){
      return $this->hasMany('App\Book');
    }
}
