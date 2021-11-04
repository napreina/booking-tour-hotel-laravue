<?php
namespace Modules\Tour\Controllers;

use Modules\FrontendController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Tour\Models\Tour;
use Modules\Tour\Models\TourCategory;
use Modules\Tour\Models\TourTranslation;
use Modules\Location\Models\Location;
use Modules\Core\Models\Attributes;
use Modules\Tour\Models\TourTerm;
use Modules\Booking\Models\Booking;

class ManageTourController extends FrontendController
{
    protected $tourClass;
    protected $tourTranslationClass;
    protected $tourCategoryClass;
    protected $tourTermClass;
    protected $attributesClass;
    protected $locationClass;
    protected $bookingClass;

    public function __construct()
    {
        $this->tourClass = Tour::class;
        $this->tourTranslationClass = TourTranslation::class;
        $this->tourCategoryClass = TourCategory::class;
        $this->tourTermClass = TourTerm::class;
        $this->attributesClass = Attributes::class;
        $this->locationClass = Location::class;
        $this->bookingClass = Booking::class;
    }
    public function manageTour(Request $request)
    {
        $this->checkPermission('tour_view');
        $user_id = Auth::id();
        $list_tour = $this->tourClass::where("create_user", $user_id)->orderBy('id', 'desc');
        $data = [
            'rows' => $list_tour->paginate(5),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Tours'),
                    'url'   => route('tour.vendor.index'),
                ],
                [
                    'name'  => __('All'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Manage Tours"),
        ];
        return view('Tour::frontend.manageTour.index', $data);
    }

    public function createTour(Request $request)
    {
        $this->checkPermission('tour_create');
        $row = new $this->tourClass();
        $data = [
            'row'           => $row,
            'translation' => new $this->tourTranslationClass(),
            'tour_category' => $this->tourCategoryClass::get()->toTree(),
            'tour_location' => $this->locationClass ::get()->toTree(),
            'attributes'    => $this->attributesClass::where('service', 'tour')->get(),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Tours'),
                    'url'   => route('tour.vendor.index'),
                ],
                [
                    'name'  => __('Create'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Create Tours"),
        ];
        return view('Tour::frontend.manageTour.detail', $data);
    }

    public function editTour(Request $request, $id)
    {
        $this->checkPermission('tour_update');
        $user_id = Auth::id();
        $row = $this->tourClass::where("create_user", $user_id);
        $row = $row->find($id);
        if (empty($row)) {
            return redirect(route('tour.vendor.index'))->with('warning', __('Tour not found!'));
        }
        $translation = $row->translateOrOrigin($request->query('lang'));
        $data = [
            'translation'    => $translation,
            'row'           => $row,
            'tour_category' => $this->tourCategoryClass::get()->toTree(),
            'tour_location' => $this->locationClass::get()->toTree(),
            'attributes'    => $this->attributesClass::where('service', 'tour')->get(),
            "selected_terms" =>  $row->tour_term->pluck('term_id'),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Tours'),
                    'url'   => route('tour.vendor.index'),
                ],
                [
                    'name'  => __('Edit'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Edit Tours"),
        ];
        return view('Tour::frontend.manageTour.detail', $data);
    }

    public function store( Request $request, $id ){
        if($id>0){
            $this->checkPermission('tour_update');
            $row = $this->tourClass::find($id);
            if (empty($row)) {
                return redirect(route('tour.vendor.edit',['id'=>$row->id]));
            }

            if($row->create_user != Auth::id() and !$this->hasPermission('tour_manage_others'))
            {
                return redirect(route('tour.vendor.edit',['id'=>$row->id]));
            }

        }else{
            $this->checkPermission('tour_create');
            $row = new $this->tourClass();
            $row->status = "publish";
            if(setting_item("tour_vendor_create_service_must_approved_by_admin", 0)){
                $row->status = "pending";
            }
        }

        $row->fillByAttr([
            'title',
            'content',
            'image_id',
            'banner_image_id',
            'short_desc',
            'category_id',
            'location_id',
            'address',
            'map_lat',
            'map_lng',
            'map_zoom',
            'gallery',
            'video',
            'default_state',
            'price',
            'sale_price',
            'duration',
            'max_people',
            'min_people',
            'faqs',
            'include',
            'exclude',
            'itinerary',
        ], $request->input());

        $res = $row->saveOriginOrTranslation($request->input('lang'),true);
        if ($res) {
            if(!$request->input('lang') or is_default_lang($request->input('lang'))) {
                $this->saveTerms($row, $request);
            }
            $row->saveMeta($request);
            if($id > 0 ){
                return back()->with('success',  __('Tour updated') );
            }else{
                return redirect(route('tour.vendor.edit',['id'=>$row->id]))->with('success', __('Tour created') );
            }
        }
    }

    public function saveTerms($row, $request)
    {
        if (empty($request->input('terms'))) {
            $this->tourTermClass::where('tour_id', $row->id)->delete();
        } else {
            $term_ids = $request->input('terms');
            foreach ($term_ids as $term_id) {
                $this->tourTermClass::firstOrCreate([
                    'term_id' => $term_id,
                    'tour_id' => $row->id
                ]);
            }
            $this->tourTermClass::where('tour_id', $row->id)->whereNotIn('term_id', $term_ids)->delete();
        }
    }

    public function deleteTour($id)
    {
        $this->checkPermission('tour_delete');
        $user_id = Auth::id();
        $query = $this->tourClass::where("create_user", $user_id)->where("id", $id)->first();
        if(!empty($query)){
            $query->delete();
        }
        return redirect(route('tour.vendor.index'))->with('success', __('Delete tour success!'));
    }

    public function bulkEditTour($id , Request $request){
        $this->checkPermission('tour_update');
        $action = $request->input('action');
        $user_id = Auth::id();
        $query = $this->tourClass::where("create_user", $user_id)->where("id", $id)->first();
        if (empty($id)) {
            return redirect()->back()->with('error', __('No item!'));
        }
        if (empty($action)) {
            return redirect()->back()->with('error', __('Please select an action!'));
        }
        if(empty($query)){
            return redirect()->back()->with('error', __('Not Found'));
        }
        switch ($action){
            case "make-hide":
                $query->status = "draft";
                break;
            case "make-publish":
                $query->status = "publish";
                break;
        }
        $query->save();
        return redirect()->back()->with('success', __('Update success!'));
    }

    public function bookingReport(Request $request)
    {
        $data = [
            'bookings' => $this->bookingClass::getBookingHistory($request->input('status'), false ,Auth::id() , 'tour'),
            'statues'  => config('booking.statuses'),
            'breadcrumbs'        => [
                [
                    'name' => __('Manage Tours'),
                    'url'   => route('tour.vendor.index'),
                ],
                [
                    'name'  => __('Booking Report'),
                    'class' => 'active'
                ],
            ],
            'page_title'         => __("Booking Report"),
        ];
        return view('Tour::frontend.manageTour.bookingReport', $data);
    }

    public function bookingReportBulkEdit($booking_id , Request $request){
        $status = $request->input('status');
        if (!empty(setting_item("tour_allow_vendor_can_change_their_booking_status")) and !empty($status) and !empty($booking_id)) {
            $query = $this->bookingClass::where("id", $booking_id);
            $query->where("vendor_id", Auth::id());
            $item = $query->first();
            if(!empty($item)){
                $item->status = $status;
                $item->save();
                $item->sendStatusUpdatedEmails();
                return redirect()->back()->with('success', __('Update success'));
            }
            return redirect()->back()->with('error', __('Booking not found!'));
        }
        return redirect()->back()->with('error', __('Update fail!'));
    }
}
