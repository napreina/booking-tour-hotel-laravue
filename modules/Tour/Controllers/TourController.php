<?php

    namespace Modules\Tour\Controllers;
    use App\Http\Controllers\Controller;
    use Modules\Tour\Models\Tour;
    use Illuminate\Http\Request;
    use Modules\Tour\Models\TourCategory;
    use Modules\Location\Models\Location;
    use Modules\Review\Models\Review;
    use Modules\Core\Models\Attributes;
    use DB;

    class TourController extends Controller
    {
        protected $tourClass;
        protected $locationClass;
        protected $tourCategoryClass;
        protected $attributesClass;

        public function __construct()
        {
            $this->tourClass = Tour::class;
            $this->locationClass = Location::class;
            $this->tourCategoryClass = TourCategory::class;
            $this->attributesClass = Attributes::class;
        }

        public function index(Request $request)
        {
            $is_ajax = $request->query('_ajax');
            $model_Tour = $this->tourClass::select("bravo_tours.*");
            $model_Tour->where("bravo_tours.status", "publish");
            if (!empty($location_id = $request->query('location_id'))) {
                $location = $this->locationClass::where('id', $location_id)->where("status", "publish")->first();
                if (!empty($location)) {
                    $model_Tour->join('bravo_locations', function ($join) use ($location) {
                        $join->on('bravo_locations.id', '=', 'bravo_tours.location_id')
                            ->where('bravo_locations._lft', '>=', $location->_lft)
                            ->where('bravo_locations._rgt', '<=', $location->_rgt);
                    });
                }
            }

			if (!empty($price_range = $request->query('price_range'))) {
				$pri_from = explode(";", $price_range)[0];
				$pri_to = explode(";", $price_range)[1];
				$raw_sql_min_max = "( (bravo_tours.sale_price > 0 and bravo_tours.sale_price >= ? ) OR (bravo_tours.sale_price <= 0 and bravo_tours.price >= ?) ) 
								AND ( (bravo_tours.sale_price > 0 and bravo_tours.sale_price <= ? ) OR (bravo_tours.sale_price <= 0 and bravo_tours.price <= ?) )";
				$model_Tour->WhereRaw($raw_sql_min_max,[$pri_from,$pri_from,$pri_to,$pri_to]);
			}
			if (!empty($category_ids = $request->query('cat_id'))) {
				if(!is_array($category_ids)) $category_ids = [$category_ids];
				$list_cat = $this->tourCategoryClass::whereIn('id', $category_ids)->where("status","publish")->get();
				if(!empty($list_cat)){
					$where_left_right = [];
					foreach ($list_cat as $cat){
						$where_left_right[] = " ( bravo_tour_category._lft >= {$cat->_lft} AND bravo_tour_category._rgt <= {$cat->_rgt} ) ";
					}
                    $sql_where_join = " ( " . implode("OR", $where_left_right) . " )  ";
                    $model_Tour
                        ->join('bravo_tour_category', function ($join) use ($sql_where_join) {
                            $join->on('bravo_tour_category.id', '=', 'bravo_tours.category_id')
                                ->WhereRaw($sql_where_join);
                        });
				}

			}
			$terms = $request->query('terms');
			if (is_array($terms) && !empty($terms)) {
				$model_Tour->join('bravo_tour_term as tt', 'tt.tour_id', "bravo_tours.id")->whereIn('tt.term_id', $terms);
			}
            $model_Tour->orderBy("id", "desc");
            $model_Tour->groupBy("bravo_tours.id");

            $list = $model_Tour->with(['location','hasWishList','translations'])->paginate(9);
            $markers = [];
            if (!empty($list)) {
                foreach ($list as $row) {
                    $markers[] = [
                        "id" => $row->id,
                        "title" => $row->title,
                        "lat" => (float)$row->map_lat,
                        "lng" => (float)$row->map_lng,
                        "gallery" => $row->getGallery(true),
                        "infobox" => view('Tour::frontend.layouts.search.loop-gird', ['row' => $row, 'disable_lazyload' => 1, 'wrap_class' => 'infobox-item'])->render(),
                        'marker' => url('images/icons/png/pin.png'),
                        //                    'marker'=>'http://travelhotel.wpengine.com/wp-content/uploads/2018/11/ico_mapker_hotel.png'
                    ];
                }
            }
            $limit_location = 15;
            if( empty(setting_item("space_location_search_style")) or setting_item("space_location_search_style") == "normal" ){
                $limit_location = 1000;
            }
            $data = [
                'rows' => $list,
                'tour_category' => $this->tourCategoryClass::where('status', 'publish')->with(['translations'])->get()->toTree(),
                'tour_location' => $this->locationClass::where('status', 'publish')->with(['translations'])->limit($limit_location)->get()->toTree(),
                'tour_min_max_price' => $this->tourClass::getMinMaxPrice(),
                'markers' => $markers,
                "blank" => 1,
                "seo_meta" => $this->tourClass::getSeoMetaForPageList()
            ];
            $layout = setting_item("tour_layout_search", 'normal');
            if ($request->query('_layout')) {
                $layout = $request->query('_layout');
            }
            if ($is_ajax) {
                $this->sendSuccess([
                    'html' => view('Tour::frontend.layouts.search-map.list-item', $data)->render(),
                    "markers" => $data['markers']
                ]);
            }
            $data['attributes'] = $this->attributesClass::where('service', 'tour')->with(['terms','translations'])->get();
            if ($layout == "map") {
                $data['body_class'] = 'has-search-map';
                $data['html_class'] = 'full-page';
                return view('Tour::frontend.search-map', $data);
            }
            return view('Tour::frontend.search', $data);
        }

        public function detail(Request $request, $slug)
        {
            $row = $this->tourClass::where('slug', $slug)->where("status", "publish")->with(['location','translations','hasWishList'])->first();
            if (empty($row)) {
                return redirect('/');
            }
            $translation = $row->translateOrOrigin(app()->getLocale());
            $tour_related = [];
            $location_id = $row->location_id;
            if (!empty($location_id)) {
                $tour_related = $this->tourClass::where('location_id', $location_id)->where("status","publish")->take(4)->whereNotIn('id', [$row->id])->with(['location','translations','hasWishList'])->get();
            }
            $review_list = Review::where('object_id', $row->id)
                ->where('object_model', 'tour')
                ->where("status", "approved")
                ->orderBy("id", "desc")
                ->with('author')
                ->paginate(setting_item('tour_review_number_per_page', 5));
            $data = [
                'row' => $row,
                'translation' => $translation,
                'tour_related' => $tour_related,
                'booking_data' => $row->getBookingData(),
                'review_list' => $review_list,
                'seo_meta' => $row->getSeoMetaWithTranslation(app()->getLocale(), $translation),
                'body_class'=>'is_single'
            ];
            $this->setActiveMenu($row);
            return view('Tour::frontend.detail', $data);
        }
    }
