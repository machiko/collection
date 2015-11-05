<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NewsUrl extends Model
{
    //
    public function newscontent()
    {
    	return $this->hasOne('App\NewsContent', 'url_id', 'id');
    }
}
