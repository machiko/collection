<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NewsContent extends Model
{
    //
    public function newsurl()
    {
    	return $this->belongsTo('App\NewsUrl', 'url_id', 'id');
    }
}
