<?php
namespace Modules\Space\Controllers;

use App\Http\Controllers\Controller;
use Modules\Space\Models\Space;
use Illuminate\Http\Request;
use Modules\Location\Models\Location;
use Modules\Review\Models\Review;
use Modules\Core\Models\Attributes;
use DB;

class SpaceController extends Controller
{
    protected $spaceClass;
    protected $locationClass;
    public function __construct()
    {
        $this->spaceClass = Space::class;
        $this->locationClass = Location::class;
    }

    public function index(Request $request)
    {

        $is_ajax = $request->query('_ajax');
        $model_space = $this->spaceClass::select("bravo_spaces.*");
        $model_space->where("bravo_spaces.status", "publish");
        if (!empty($location_id = $request->query('location_id'))) {
            $location = $this->locationClass::where('id', $location_id)->where("status","publish")->first();
            if(!empty($location)){
                $model_space->join('bravo_locations', function ($join) use ($location) {
                    $join->on('bravo_locations.id', '=', 'bravo_spaces.location_id')
                        ->where('bravo_locations._lft', '>=', $location->_lft)
                        ->where('bravo_locations._rgt', '<=', $location->_rgt);
                });
            }
        }
        if (!empty($price_range = $request->query('price_range'))) {
            $pri_from = explode(";", $price_range)[0];
            $pri_to = explode(";", $price_range)[1];
            $raw_sql_min_max = "( (bravo_spaces.sale_price > 0 and bravo_spaces.sale_price >= ? ) OR (bravo_spaces.sale_price <= 0 and bravo_spaces.price >= ? ) ) 
                            AND ( (bravo_spaces.sale_price > 0 and bravo_spaces.sale_price <= ? ) OR (bravo_spaces.sale_price <= 0 and bravo_spaces.price <= ? ) )";
            $model_space->WhereRaw($raw_sql_min_max,[$pri_from,$pri_from,$pri_to,$pri_to]);
        }

        $terms = $request->query('terms');
        if (is_array($terms) && !empty($terms)) {
            $model_space->join('bravo_space_term as tt', 'tt.target_id', "bravo_spaces.id")->whereIn('tt.term_id', $terms);
        }
        $model_space->orderBy("id", "desc");
        $model_space->groupBy("bravo_spaces.id");

        $max_guests = (int)($request->query('adults') + $request->query('children'));
        if($max_guests){
            $model_space->where('max_guests','>=',$max_guests);
        }

        $list = $model_space->with(['location','hasWishList','translations'])->paginate(9);
        $markers = [];
        if (!empty($list)) {
            foreach ($list as $row) {
                $markers[] = [
                    "id"      => $row->id,
                    "title"   => $row->title,
                    "lat"     => (float)$row->map_lat,
                    "lng"     => (float)$row->map_lng,
                    "gallery" => $row->getGallery(true),
                    "infobox" => view('Space::frontend.layouts.search.loop-gird', ['row' => $row,'disable_lazyload'=>1,'wrap_class'=>'infobox-item'])->render(),
                    'marker'  => url('images/icons/png/pin.png'),
                    //                    'marker'=>'http://travelhotel.wpengine.com/wp-content/uploads/2018/11/ico_mapker_hotel.png'
                ];
            }
        }
        $limit_location = 15;
        if( empty(setting_item("space_location_search_style")) or setting_item("space_location_search_style") == "normal" ){
            $limit_location = 1000;
        }
        $data = [
            'rows'               => $list,
            'list_location'      => $this->locationClass::where('status', 'publish')->limit($limit_location)->with(['translations'])->get()->toTree(),
            'space_min_max_price' => $this->spaceClass::getMinMaxPrice(),
            'markers'            => $markers,
            "blank"              => 1,
            "seo_meta"           => $this->spaceClass::getSeoMetaForPageList()
        ];
        $layout = setting_item("space_layout_search", 'normal');
        if ($request->query('_layout')) {
            $layout = $request->query('_layout');
        }
        if ($is_ajax) {
            $this->sendSuccess([
                'html'    => view('Space::frontend.layouts.search-map.list-item', $data)->render(),
                "markers" => $data['markers']
            ]);
        }
        $data['attributes'] = Attributes::where('service', 'space')->with(['terms','translations'])->get();

        if ($layout == "map") {
            $data['body_class'] = 'has-search-map';
            $data['html_class'] = 'full-page';
            return view('Space::frontend.search-map', $data);
        }
        return view('Space::frontend.search', $data);
    }

    public function detail(Request $request, $slug)
    {
        $row = $this->spaceClass::where('slug', $slug)->where("status", "publish")->with(['location','translations','hasWishList'])->first();;
        if (empty($row)) {
            return redirect('/');
        }
        $translation = $row->translateOrOrigin(app()->getLocale());
        $space_related = [];
        $location_id = $row->location_id;
        if (!empty($location_id)) {
            $space_related = $this->spaceClass::where('location_id', $location_id)->where("status", "publish")->take(4)->whereNotIn('id', [$row->id])->with(['location','translations','hasWishList'])->get();
        }
        $review_list = Review::where('object_id', $row->id)->where('object_model', 'space')->where("status", "approved")->orderBy("id", "desc")->with('author')->paginate(setting_item('space_review_number_per_page', 5));
        $data = [
            'row'          => $row,
            'translation'       => $translation,
            'space_related' => $space_related,
            'booking_data' => $row->getBookingData(),
            'review_list'  => $review_list,
            'seo_meta'  => $row->getSeoMetaWithTranslation(app()->getLocale(),$translation),
            'body_class'=>'is_single'
        ];
        $this->setActiveMenu($row);
        return view('Space::frontend.detail', $data);
    }
}
