<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = ['title','author_id','amount'];

    public function authors(){
      return $this->belongsTo('App\Author');
    }
}
