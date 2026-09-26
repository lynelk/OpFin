<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssetPassport extends Model {
 protected $fillable=['reference','financial_space_id','owner_user_id','asset_class','asset_subclass','make','model','external_identifier_hash','identifier_evidence','ownership_evidence','valuation','status'];
 protected function casts(): array { return ['identifier_evidence'=>'array','ownership_evidence'=>'array','valuation'=>'array']; }
}