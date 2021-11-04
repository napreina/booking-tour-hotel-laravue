<?php

namespace Modules\Hotel\Models;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Modules\Booking\Models\Bookable;
use Modules\Booking\Models\Booking;
use Modules\Core\Models\Attributes;
use Modules\Core\Models\SEO;
use Modules\Core\Models\Terms;
use Modules\Media\Helpers\FileHelper;
use Modules\Review\Models\Review;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Hotel\Models\HotelTranslation;
use Modules\User\Models\UserWishList;

class Hotel extends Bookable
{
    use SoftDeletes;
    protected $table = 'bravo_hotels';
    public $type = 'hotel';
    public $checkout_booking_detail_file       = 'Hotel::frontend/booking/detail';
    public $checkout_booking_detail_modal_file = 'Hotel::frontend/booking/detail-modal';
    public $email_new_booking_file             = 'Hotel::emails.new_booking_detail';

    protected $fillable = [
        'title',
        'content',
        'status',
    ];
    protected $slugField     = 'slug';
    protected $slugFromField = 'title';
    protected $seo_type = 'hotel';

    protected $casts = [
        'policy'  => 'array',
    ];


    protected $bookingClass;
    protected $reviewClass;
    protected $hotelDateClass;
    protected $hotelTermClass;
    protected $hotelTranslationClass;
    protected $userWishListClass;
    protected $termClass;
    protected $attributeClass;
    protected $roomClass;

    protected $tmp_rooms = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->bookingClass = Booking::class;
        $this->reviewClass = Review::class;
        $this->hotelTermClass = HotelTerm::class;
        $this->hotelTranslationClass = HotelTranslation::class;
        $this->userWishListClass = UserWishList::class;
        $this->termClass = Terms::class;
        $this->attributeClass = Attributes::class;
        $this->roomClass = HotelRoom::class;
    }

    public static function getModelName()
    {
        return __("Hotel");
    }

    public static function getTableName()
    {
        return with(new static)->table;
    }


    /**
     * Get SEO fop page list
     *
     * @return mixed
     */
    static public function getSeoMetaForPageList()
    {
        $meta['seo_title'] = __("Search for Spaces");
        if (!empty($title = setting_item_with_lang("hotel_page_list_seo_title",false))) {
            $meta['seo_title'] = $title;
        }else if(!empty($title = setting_item_with_lang("hotel_page_search_title"))) {
            $meta['seo_title'] = $title;
        }
        $meta['seo_image'] = null;
        if (!empty($title = setting_item("hotel_page_list_seo_image"))) {
            $meta['seo_image'] = $title;
        }else if(!empty($title = setting_item("hotel_page_search_banner"))) {
            $meta['seo_image'] = $title;
        }
        $meta['seo_desc'] = setting_item_with_lang("hotel_page_list_seo_desc");
        $meta['seo_share'] = setting_item_with_lang("hotel_page_list_seo_share");
        $meta['full_url'] = url(config('hotel.hotel_route_prefix'));
        return $meta;
    }


    public function terms(){
        return $this->hasMany($this->hotelTermClass, "target_id");
    }

    public function termsByAttributeInListingPage(){
        $attribute = setting_item("hotel_attribute_show_in_listing_page",0);
        return $this->hasManyThrough($this->termClass, $this->hotelTermClass,'target_id','id','id','term_id')->where('bravo_terms.attr_id',$attribute)->with(['translations']);
    }
    public function getAttributeInListingPage(){
        $attribute_id = setting_item("hotel_attribute_show_in_listing_page",0);
        $attribute = $this->attributeClass::find($attribute_id);
        return $attribute ?? false;
    }

    public function getDetailUrl($include_param = true)
    {
        $param = [];
        if($include_param){
            if(!empty($date =  request()->input('date'))){
                $dates = explode(" - ",$date);
                if(!empty($dates)){
                    $param['start'] = $dates[0] ?? "";
                    $param['end'] = $dates[1] ?? "";
                }
            }
            if(!empty($adults =  request()->input('adults'))){
                $param['adults'] = $adults;
            }
            if(!empty($children =  request()->input('children'))){
                $param['children'] = $children;
            }
            if(!empty($room =  request()->input('room'))){
                $param['room'] = $room;
            }
        }
        $urlDetail = app_get_locale(false, false, '/') . config('hotel.hotel_route_prefix') . "/" . $this->slug;
        if(!empty($param)){
            $urlDetail .= "?".http_build_query($param);
        }
        return url($urlDetail);
    }

    public static function getLinkForPageSearch( $locale = false , $param = [] ){

        return url(app_get_locale(false , false , '/'). config('hotel.hotel_route_prefix')."?".http_build_query($param));
    }

    public function getGallery($featuredIncluded = false)
    {
        if (empty($this->gallery))
            return $this->gallery;
        $list_item = [];
        if ($featuredIncluded and $this->image_id) {
            $list_item[] = [
                'large' => FileHelper::url($this->image_id, 'full'),
                'thumb' => FileHelper::url($this->image_id, 'thumb')
            ];
        }
        $items = explode(",", $this->gallery);
        foreach ($items as $k => $item) {
            $large = FileHelper::url($item, 'full');
            $thumb = FileHelper::url($item, 'thumb');
            $list_item[] = [
                'large' => $large,
                'thumb' => $thumb
            ];
        }
        return $list_item;
    }

    public function getEditUrl()
    {
        return url(route('hotel.admin.edit',['id'=>$this->id]));
    }

    public function getDiscountPercentAttribute()
    {
        if (    !empty($this->price) and $this->price > 0
            and !empty($this->sale_price) and $this->sale_price > 0
            and $this->price > $this->sale_price
        ) {
            $percent = 100 - ceil($this->sale_price / ($this->price / 100));
            return $percent . "%";
        }
    }

    public function fill(array $attributes)
    {
        if(!empty($attributes)){
            foreach ( $this->fillable as $item ){
                $attributes[$item] = $attributes[$item] ?? null;
            }
        }
        return parent::fill($attributes); // TODO: Change the autogenerated stub
    }

    public function isBookable()
    {
        if ($this->status != 'publish')
            return false;
        return parent::isBookable();
    }

    public function addToCart(Request $request)
    {
        $this->addToCartValidate($request);
        // Add Booking
        $total_guests = $request->input('adults') + $request->input('children');
        $discount = 0;
        $start_date = new \DateTime($request->input('start_date'));
        $end_date = new \DateTime($request->input('end_date'));

        $total = 0;
        if(!empty($this->tmp_selected_rooms)){
            foreach ($this->tmp_selected_rooms as $room){
                if(isset($this->tmp_rooms_by_id[$room['id']]))
                {
                    $total += $this->tmp_rooms_by_id[$room['id']]->tmp_price * $room['number_selected'];
                }
            }
        }

        //Buyer Fees
        $total_before_fees = $total;
        $list_fees = setting_item('hotel_booking_buyer_fees');
        if(!empty($list_fees)){
            $lists = json_decode($list_fees,true);
            foreach ($lists as $item){
                if(!empty($item['per_person']) and $item['per_person'] == "on"){
                    $total += $item['price'] * $total_guests;
                }else{
                    $total += $item['price'];
                }
            }
        }
        $booking = new $this->bookingClass();
        $booking->status = 'draft';
        $booking->object_id = $request->input('service_id');
        $booking->object_model = $request->input('service_type');
        $booking->vendor_id = $this->create_user;
        $booking->customer_id = Auth::id();
        $booking->total = $total;
        $booking->total_guests = $total_guests;
        $booking->start_date = $start_date->format('Y-m-d H:i:s');
        $booking->end_date = $end_date->format('Y-m-d H:i:s');
        $booking->buyer_fees = $list_fees ?? '';
        $booking->total_before_fees = $total_before_fees;
        $booking->calculateCommission();

        $check = $booking->save();
        if ($check) {

            $this->bookingClass::clearDraftBookings();

            $booking->addMeta('duration', $this->duration);
            $booking->addMeta('base_price', $this->price);
            $booking->addMeta('sale_price', $this->sale_price);
            $booking->addMeta('guests', $total_guests);
            $booking->addMeta('adults', $request->input('adults'));
            $booking->addMeta('children', $request->input('children'));

            // Add Room Booking
            if(!empty($this->tmp_selected_rooms)){
                foreach ($this->tmp_selected_rooms as $room){
                    if(isset($this->tmp_rooms_by_id[$room['id']]))
                    {
                        $hotelRoomBooking = new HotelRoomBooking();
                        $hotelRoomBooking->fillByAttr([
                            'room_id','parent_id','start_date','end_date','number','booking_id','price'
                        ],[
                            'room_id'=>$room['id'],
                            'parent_id'=>$this->id,
                            'start_date'=>$start_date->format('Y-m-d H:i:s'),
                            'end_date'=>$end_date->format('Y-m-d H:i:s'),
                            'number'=>$room['number_selected'],
                            'booking_id'=>$booking->id,
                            'price'=>$this->tmp_rooms_by_id[$room['id']]->tmp_price
                        ]);

                        $hotelRoomBooking->save();
                    }
                }
            }

            $this->sendSuccess([
                'url' => $booking->getCheckoutUrl()
            ]);
        }
        $this->sendError(__("Can not check availability"));
    }

    public function getPriceInRanges($start_date,$end_date){
        $totalPrice = 0;
        $price = ($this->sale_price and $this->sale_price > 0 and  $this->sale_price < $this->price) ? $this->sale_price : $this->price;

        $datesRaw = $this->hotelDateClass::getDatesInRanges($start_date,$end_date);
        $dates = [];
        if(!empty($datesRaw))
        {
            foreach ($datesRaw as $date){
                $dates[date('Y-m-d',strtotime($date['start_date']))] = $date;
            }
        }

        if(strtotime($start_date) == strtotime($end_date))
        {
            if(empty($dates[date('Y-m-d',strtotime($start_date))]))
            {
                $totalPrice += $price;
            }else{
                $totalPrice += $dates[date('Y-m-d',strtotime($start_date))]->price;
            }
            return $totalPrice;
        }

        for($i = strtotime($start_date); $i < strtotime($end_date); $i += DAY_IN_SECONDS){
            if(empty($dates[date('Y-m-d',$i)]))
            {
                $totalPrice += $price;
            }else{
                $totalPrice += $dates[date('Y-m-d',$i)]->price;
            }
        }

        return $totalPrice;
    }

    public function addToCartValidate(Request $request)
    {
        $rules = [
            'rooms'     => 'required',
            'adults'     => 'required|integer|min:1',
            'children'     => 'required|integer|min:0',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d'
        ];

        // Validation
        if (!empty($rules)) {
            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                $this->sendError('', ['errors' => $validator->errors()]);
            }

        }

        $total_rooms = array_sum(array_column($request->input('rooms','number_selected') , "number_selected"));
        $selected_rooms = Arr::where($request->input('rooms'), function ($value, $key) {
            return !empty($value['number_selected']) and $value['number_selected'] > 0;
        });

        if($total_rooms <=0 or empty($selected_rooms)){
            $this->sendError(__("Please select at lease one room"));
        }

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');

        if(strtotime($start_date) < strtotime(date('Y-m-d 00:00:00')) or strtotime($end_date) - strtotime($start_date) < DAY_IN_SECONDS)
        {
            $this->sendError(__("Your selected dates are not valid"));
        }

        // Validate Date and Booking
        $rooms = $this->getRoomsAvailability(request()->input());
        $rooms_by_id = [];
        if(empty($rooms)) $this->sendError(__("There is no room available at your selected dates"));
        foreach ($this->tmp_rooms as $room){
            $rooms_by_id[$room['id']] = $room;
        }

        $rooms_ids = array_column($rooms,'id');

        foreach ($selected_rooms as $room){
            if(!in_array($room['id'],$rooms_ids) or $room['number_selected'] > $rooms_by_id[$room['id']]->tmp_number){
                $this->sendError(__("Your selected room is not available. Please search again"));
            }
        }

        $this->tmp_rooms_by_id = $rooms_by_id;
        $this->tmp_selected_rooms = $selected_rooms;

        return true;
    }

    public function isAvailableInRanges($start_date,$end_date){

        $days = max(1,floor((strtotime($end_date) - strtotime($start_date)) / DAY_IN_SECONDS));

        if($this->default_state)
        {
            $notAvailableDates = $this->hotelDateClass::query()->where([
                ['start_date','>=',$start_date],
                ['end_date','<=',$end_date],
                ['active','0']
            ])->count('id');
            if($notAvailableDates) return false;

        }else{
            $availableDates = $this->hotelDateClass::query()->where([
                ['start_date','>=',$start_date],
                ['end_date','<=',$end_date],
                ['active','=',1]
            ])->count('id');
            if($availableDates <= $days) return false;
        }

        // Check Order
        $bookingInRanges = $this->bookingClass::getAcceptedBookingQuery($this->id,$this->type)->where([
            ['end_date','>=',$start_date],
            ['start_date','<=',$end_date],
        ])->count('id');

        if($bookingInRanges){
            return false;
        }

        return true;
    }

    public function getBookingData()
    {

        if (!empty($start = request()->input('start'))) {
            $start_html = display_date($start);
            $end_html = request()->input('end') ? display_date(request()->input('end')) : "";
            $date_html = $start_html . '<i class="fa fa-long-arrow-right" style="font-size: inherit"></i>' . $end_html;
        }

        $booking_data = [
            'id'           => $this->id,
            'person_types' => [],
            'max'          => 0,
            'open_hours'   => [],
            'extra_price'  => [],
            'minDate'      => date('m/d/Y'),
            'max_guests' =>$this->max_guests  ?? 1,
            'buyer_fees'=>[],
            'i18n'=>[
                'date_required'=>__("Please select check-in and check-out date")
            ],
            'start_date'      => request()->input('start') ?? "",
            'start_date_html' => $date_html ?? __('Please select'),
            'end_date'        => request()->input('end') ?? "",
        ];
        if(!empty( $adults = request()->input('adults') )){
            $booking_data['adults'] = $adults;
        }
        if(!empty( $children = request()->input('children') )){
            $booking_data['children'] = $children;
        }
        if(!empty( $children = request()->input('room') )){
            $booking_data['room'] = $children;
        }
        if ($this->enable_extra_price) {
            $booking_data['extra_price'] = $this->extra_price;
            if (!empty($booking_data['extra_price'])) {
                foreach ($booking_data['extra_price'] as $k => &$type) {
                    if (!empty($lang) and !empty($type['name_' . $lang])) {
                        $type['name'] = $type['name_' . $lang];
                    }
                    $type['number'] = 0;
                    $type['enable'] = 0;
                    $type['price_html'] = format_money($type['price']);
                    $type['price_type'] = '';
                    switch ($type['type']) {
                        case "per_day":
                            $type['price_type'] .= '/' . __('day');
                            break;
                        case "per_hour":
                            $type['price_type'] .= '/' . __('hour');
                            break;
                    }
                    if (!empty($type['per_person'])) {
                        $type['price_type'] .= '/' . __('guest');
                    }
                }
            }

            $booking_data['extra_price'] = array_values((array)$booking_data['extra_price']);
        }

        $list_fees = setting_item_array('hotel_booking_buyer_fees');
        if(!empty($list_fees)){
            foreach ($list_fees as $item){
                $item['type_name'] = $item['name_'.app()->getLocale()] ?? $item['name'] ?? '';
                $item['type_desc'] = $item['desc_'.app()->getLocale()] ?? $item['desc'] ?? '';
                $item['price_type'] = '';
                if (!empty($item['per_person']) and $item['per_person'] == 'on') {
                    $item['price_type'] .= '/' . __('guest');
                }
                $booking_data['buyer_fees'][] = $item;
            }
        }
        return $booking_data;
    }

    public static function searchForMenu($q = false)
    {
        $query = static::select('id', 'title as name');
        if (strlen($q)) {

            $query->where('title', 'like', "%" . $q . "%");
        }
        $a = $query->limit(10)->get();
        return $a;
    }

    public static function getMinMaxPrice()
    {
        $model = parent::selectRaw('MIN( price ) AS min_price ,
                                    MAX( price ) AS max_price ')->where("status", "publish")->first();
        if (empty($model->min_price) and empty($model->max_price)) {
            return [
                0,
                100
            ];
        }
        return [
            $model->min_price,
            $model->max_price
        ];
    }

    public function getReviewEnable()
    {
        return setting_item("hotel_enable_review", 0);
    }

    public function getReviewApproved()
    {
        return setting_item("hotel_review_approved", 0);
    }

    public function check_enable_review_after_booking()
    {
        $option = setting_item("hotel_enable_review_after_booking", 0);
        if ($option) {
            $number_review = $this->reviewClass::countReviewByServiceID($this->id, Auth::id()) ?? 0;
            $number_booking = $this->bookingClass::countBookingByServiceID($this->id, Auth::id()) ?? 0;
            if ($number_review >= $number_booking) {
                return false;
            }
        }
        return true;
    }

    public static function getReviewStats()
    {
        $reviewStats = [];
        if (!empty($list = setting_item("hotel_review_stats", []))) {
            $list = json_decode($list, true);
            foreach ($list as $item) {
                $reviewStats[] = $item['title'];
            }
        }
        return $reviewStats;
    }

    public function getReviewDataAttribute()
    {
        $list_score = [
            'score_total'  => 0,
            'score_text'   => __("Not rated"),
            'total_review' => 0,
            'rate_score'   => [],
        ];
        $dataTotalReview = $this->reviewClass::selectRaw(" AVG(rate_number) as score_total , COUNT(id) as total_review ")->where('object_id', $this->id)->where('object_model', $this->type)->where("status", "approved")->first();
        if (!empty($dataTotalReview->score_total)) {
            $list_score['score_total'] = number_format($dataTotalReview->score_total, 1);
            $list_score['score_text'] = Review::getDisplayTextScoreByLever(round($list_score['score_total']));
        }
        if (!empty($dataTotalReview->total_review)) {
            $list_score['total_review'] = $dataTotalReview->total_review;
        }
        $list_data_rate = $this->reviewClass::selectRaw('COUNT( CASE WHEN rate_number = 5 THEN rate_number ELSE NULL END ) AS rate_5,
                                                            COUNT( CASE WHEN rate_number = 4 THEN rate_number ELSE NULL END ) AS rate_4,
                                                            COUNT( CASE WHEN rate_number = 3 THEN rate_number ELSE NULL END ) AS rate_3,
                                                            COUNT( CASE WHEN rate_number = 2 THEN rate_number ELSE NULL END ) AS rate_2,
                                                            COUNT( CASE WHEN rate_number = 1 THEN rate_number ELSE NULL END ) AS rate_1 ')->where('object_id', $this->id)->where('object_model', $this->type)->where("status", "approved")->first()->toArray();
        for ($rate = 5; $rate >= 1; $rate--) {
            if (!empty($number = $list_data_rate['rate_' . $rate])) {
                $percent = ($number / $list_score['total_review']) * 100;
            } else {
                $percent = 0;
            }
            $list_score['rate_score'][$rate] = [
                'title'   => $this->reviewClass::getDisplayTextScoreByLever($rate),
                'total'   => $number,
                'percent' => round($percent),
            ];
        }
        return $list_score;
    }

    /**
     * Get Score Review
     *
     * Using for loop hotel
     */
    public function getScoreReview()
    {
        $hotel_id = $this->id;
        $list_score = Cache::rememberForever('review_'.$this->type.'_' . $hotel_id, function () use ($hotel_id) {
            $dataReview = $this->reviewClass::selectRaw(" AVG(rate_number) as score_total , COUNT(id) as total_review ")->where('object_id', $hotel_id)->where('object_model', "hotel")->where("status", "approved")->first();
            $score_total = !empty($dataReview->score_total) ? number_format($dataReview->score_total, 1) : 0;
            return [
                'score_total'  => $score_total,
                'total_review' => !empty($dataReview->total_review) ? $dataReview->total_review : 0,
            ];
        });
        $list_score['review_text'] =  $list_score['score_total'] ? Review::getDisplayTextScoreByLever( round( $list_score['score_total'] )) : __("Not rated");
        return $list_score;
    }

    public function getNumberReviewsInService($status = false)
    {
        return $this->reviewClass::countReviewByServiceID($this->id, false, $status,$this->type) ?? 0;
    }

    public function getNumberServiceInLocation($location)
    {
        $number = 0;
        if(!empty($location)) {
            $number = parent::join('bravo_locations', function ($join) use ($location) {
                $join->on('bravo_locations.id', '=', $this->table.'.location_id')->where('bravo_locations._lft', '>=', $location->_lft)->where('bravo_locations._rgt', '<=', $location->_rgt);
            })->where($this->table.".status", "publish")->with(['translations'])->count($this->table.".id");
        }

        if(empty($number)) return false;
        if ($number > 1) {
            return __(":number Hotels", ['number' => $number]);
        }
        return __(":number Hotel", ['number' => $number]);
    }

    /**
     * @param $from
     * @param $to
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getBookingsInRange($from,$to){

        $query = $this->bookingClass::query();
        $query->whereNotIn('status',['draft']);
        $query->where('start_date','<=',$to)->where('end_date','>=',$from)->take(50);

        $query->where('object_id',$this->id);
        $query->where('object_model',$this->type);

        return $query->orderBy('id','asc')->get();

    }

    public function saveCloneByID($clone_id){
        $old = parent::find($clone_id);
        if(empty($old)) return false;
        $selected_terms = $old->terms->pluck('term_id');
        $old->title = $old->title." - Copy";
        $new = $old->replicate();
        $new->save();
        //Terms
        foreach ($selected_terms as $term_id) {
            $this->hotelTermClass::firstOrCreate([
                'term_id' => $term_id,
                'target_id' => $new->id
            ]);
        }
        //Language
        $langs = $this->hotelTranslationClass::where("origin_id",$old->id)->get();
        if(!empty($langs)){
            foreach ($langs as $lang){
                $langNew = $lang->replicate();
                $langNew->origin_id = $new->id;
                $langNew->save();
                $langSeo = SEO::where('object_id', $lang->id)->where('object_model', $lang->getSeoType()."_".$lang->locale)->first();
                if(!empty($langSeo)){
                    $langSeoNew = $langSeo->replicate();
                    $langSeoNew->object_id = $langNew->id;
                    $langSeoNew->save();
                }
            }
        }
        //SEO
        $metaSeo = SEO::where('object_id', $old->id)->where('object_model', $this->seo_type)->first();
        if(!empty($metaSeo)){
            $metaSeoNew = $metaSeo->replicate();
            $metaSeoNew->object_id = $new->id;
            $metaSeoNew->save();
        }
    }

    public function hasWishList(){
        return $this->hasOne($this->userWishListClass, 'object_id','id')->where('object_model' , $this->type)->where('user_id' , Auth::id() ?? 0);
    }

    public function isWishList()
    {
        if(Auth::id()){
            if(!empty($this->hasWishList) and !empty($this->hasWishList->id)){
                return 'active';
            }
        }
        return '';
    }

    public static function getServiceIconFeatured(){
        return "fa fa-building-o";
    }

    public function rooms(){
        return $this->hasMany($this->roomClass,'parent_id')->where('status',"publish")->with("translations");
    }
    public function getRoomsAvailability($filters = []){
        $rooms = $this->rooms;
        $res = [];
        $this->tmp_rooms = [];
        foreach($rooms as $room){
            if($room->isAvailableAt($filters)){
                $res[] = [
                    'id'=>$room->id,
                    'title'=>$room->title,
                    'price'=>$room->tmp_price ?? 0,
                    'size_html'=>$room->size ? size_unit_format($room->size) : '',
                    'beds_html'=>$room->beds ? 'x'.$room->beds : '',
                    'adults_html'=>$room->adults ? 'x'.$room->adults : '',
                    'children_html'=>$room->children ? 'x'.$room->children : '',
                    'number_selected'=>0,
                    'number'=>$room->tmp_number ?? 0,
                    'image'=>$room->image_id ? get_file_url($room->image_id,'medium') :'',
                    'tmp_number'=>$room->tmp_number,
                    'gallery'=>$room->getGallery(),
                    'price_html'=>format_money($room->tmp_price).'<span class="unit">/'.($room->tmp_nights ? __(':count nights',['count'=>$room->tmp_nights]) : __(":count night",['count'=>$room->tmp_nights])).'</span>'
                ];
                $this->tmp_rooms[] = $room;
            }
        }
        return $res;
    }
}
