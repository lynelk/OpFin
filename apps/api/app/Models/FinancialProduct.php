<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinancialProduct extends Model {
 protected $fillable=['reference','code','version','name','rail','family','contract_type','jurisdiction','currency','partner_id','legal_product_passport_id','sharia_approval_id','customer_classes','purpose_rules','policy_versions','settlement_model','asset_supplier_requirements','disclosure','status','effective_from','effective_to'];
 protected function casts(): array { return ['version'=>'integer','customer_classes'=>'array','purpose_rules'=>'array','policy_versions'=>'array','settlement_model'=>'array','asset_supplier_requirements'=>'array','disclosure'=>'array','effective_from'=>'datetime','effective_to'=>'datetime']; }
}