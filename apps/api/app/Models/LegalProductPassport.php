<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LegalProductPassport extends Model {
 protected $fillable=['reference','jurisdiction','regulated_activity','booking_entity','partner_id','funding_entity','servicer','licence_or_approval_reference','restrictions','tax_accounting_references','status','effective_from','effective_to','approved_by','approved_at'];
 protected function casts(): array { return ['restrictions'=>'array','tax_accounting_references'=>'array','effective_from'=>'datetime','effective_to'=>'datetime','approved_at'=>'datetime']; }
}