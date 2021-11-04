<?php
namespace Modules\Hotel\Controllers;

use App\Http\Controllers\Controller;
use Modules\Hotel\Models\Hotel;
use Illuminate\Http\Request;
use Modules\Location\Models\Location;
use Modules\Review\Models\Review;
use Modules\Core\Models\Attributes;
use DB;

class HotelController extends Controller
{
    protected $hotelClass;
    protected $locationClass;
    public function __construct()
    {
        $this->hotelClass = Hotel::class;
        $this->locationClass = Location::class;
    }

    public function index(Request $request)
    {

        $is_ajax = $request->query('_ajax');
        $model_hotel = $this->hotelClass::select("bravo_hotels.*");
        $model_hotel->where("bravo_hotels.status", "publish");
        if (!empty($location_id = $request->query('location_id'))) {
            $location = $this->locationClass::where('id', $location_id)->where("status","publish")->first();
            if(!empty($location)){
                $model_hotel->join('bravo_locations', function ($join) use ($location) {
                    $join->on('bravo_locations.id', '=', 'bravo_hotels.location_id')
                        ->where('bravo_locations._lft', '>=', $location->_lft)
                        ->where('bravo_locations._rgt', '<=', $location->_rgt);
                });
            }
        }
        if (!empty($price_range = $request->query('price_range'))) {
            $pri_from = explode(";", $price_range)[0];
            $pri_to = explode(";", $price_range)[1];
            $raw_sql_min_max = "(  bravo_hotels.price >= ? ) 
                            AND (  bravo_hotels.price <= ? )";
            $model_hotel->WhereRaw($raw_sql_min_max,[$pri_from,$pri_to]);
        }

        if (!empty($star_rate = $request->query('star_rate'))) {
            $model_hotel->WhereIn('star_rate',$star_rate);
        }

        $terms = $request->query('terms');
        if (is_array($terms) && !empty($terms)) {
            $model_hotel->join('bravo_hotel_term as tt', 'tt.target_id', "bravo_hotels.id")->whereIn('tt.term_id', $terms);
        }

        $model_hotel->orderBy("id", "desc");
        $model_hotel->groupBy("bravo_hotels.id");

        $list = $model_hotel->with(['location','hasWishList','translations','termsByAttributeInListingPage'])->paginate(9);
        $markers = [];
        if (!empty($list)) {
            foreach ($list as $row) {
                $markers[] = [
                    "id"      => $row->id,
                    "title"   => $row->title,
                    "lat"     => (float)$row->map_lat,
                    "lng"     => (float)$row->map_lng,
                    "gallery" => $row->getGallery(true),
                    "infobox" => view('Hotel::frontend.layouts.search.loop-grid', ['row' => $row,'disable_lazyload'=>1,'wrap_class'=>'infobox-item'])->render(),
                    'marker'  => url('images/icons/png/pin.png'),
                ];
            }
        }
        $limit_location = 15;
        if( empty(setting_item("hotel_location_search_style")) or setting_item("hotel_location_search_style") == "normal" ){
            $limit_location = 1000;
        }
        $data = [
            'rows'               => $list,
            'list_location'      => $this->locationClass::where('status', 'publish')->limit($limit_location)->with(['translations'])->get()->toTree(),
            'hotel_min_max_price' => $this->hotelClass::getMinMaxPrice(),
            'markers'            => $markers,
            "blank"              => 1,
            "seo_meta"           => $this->hotelClass::getSeoMetaForPageList()
        ];
        $layout = setting_item("hotel_layout_search", 'normal');
        if ($request->query('_layout')) {
            $layout = $request->query('_layout');
        }
        if ($is_ajax) {
            $this->sendSuccess([
                'html'    => view('Hotel::frontend.layouts.search-map.list-item', $data)->render(),
                "markers" => $data['markers']
            ]);
        }
        $data['attributes'] = Attributes::where('service', 'hotel')->with(['terms','translations'])->get();

        if ($layout == "map") {
            $data['body_class'] = 'has-search-map';
            $data['html_class'] = 'full-page';
            return view('Hotel::frontend.search-map', $data);
        }
        return view('Hotel::frontend.search', $data);
    }

    public function detail(Request $request, $slug)
    {
        $row = $this->hotelClass::where('slug', $slug)->where("status", "publish")->with(['location','translations','hasWishList'])->first();;
        if (empty($row)) {
            return redirect('/');
        }
        $translation = $row->translateOrOrigin(app()->getLocale());
        $hotel_related = [];
        $location_id = $row->location_id;
        if (!empty($location_id)) {
            $hotel_related = $this->hotelClass::where('location_id', $location_id)->where("status", "publish")->take(4)->whereNotIn('id', [$row->id])->with(['location','translations','hasWishList'])->get();
        }
        $review_list = Review::where('object_id', $row->id)->where('object_model', 'hotel')->where("status", "approved")->orderBy("id", "desc")->with('author')->paginate(setting_item('hotel_review_number_per_page', 5));
        $data = [
            'row'          => $row,
            'translation'       => $translation,
            'hotel_related' => $hotel_related,
            'booking_data' => $row->getBookingData(),
            'review_list'  => $review_list,
            'seo_meta'  => $row->getSeoMetaWithTranslation(app()->getLocale(),$translation),
            'body_class'=>'is_single'
        ];
        $this->setActiveMenu($row);
        return view('Hotel::frontend.detail', $data);
    }

    public function checkAvailability(){
        $hotel_id = \request('hotel_id');

        if(!\request()->input('firstLoad')) {
            request()->validate([
                'hotel_id'   => 'required',
                'start_date' => 'required:date_format:Y-m-d',
                'end_date'   => 'required:date_format:Y-m-d',
                'adults'     => 'required',
            ]);

            if(strtotime(\request('end_date')) - strtotime(\request('start_date')) < DAY_IN_SECONDS){
                $this->sendError(__("Dates are not valid"));
            }
            if(strtotime(\request('end_date')) - strtotime(\request('start_date')) > 30*DAY_IN_SECONDS){
                $this->sendError(__("Maximum day for booking is 30"));
            }
        }

        $hotel = $this->hotelClass::find($hotel_id);
        if(empty($hotel_id) or empty($hotel)){
            $this->sendError(__("Hotel not found"));
        }

        $rooms = $hotel->getRoomsAvailability(request()->input());

        $this->sendSuccess([
            'rooms'=>$rooms
        ]);
    }
}
