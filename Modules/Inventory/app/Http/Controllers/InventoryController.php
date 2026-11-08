<?php
namespace Modules\Inventory\Http\Controllers;
use Illuminate\Routing\Controller;
class InventoryController extends Controller
{
    public function storeReceipt() { return back(); }
    public function storeIssue() { return back(); }
    public function storeTransfer() { return back(); }
}
