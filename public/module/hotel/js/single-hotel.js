(function ($) {
    var hotelRoomForm = new Vue({
        el:'#hotel-rooms',
        data:{
            id:'',
            extra_price:[],
            person_types:[
                [

                ]
            ],
            buyer_fees:[],
            message:{
                content:'',
                type:false
            },
            html:'',
            onSubmit:false,
            start_date:'',
            end_date:'',
            start_date_html:'',
            number_of_guests:0,
            step:1,
            start_date_obj:'',
            adults:1,
            children:0,
            allEvents:[],
            rooms:[],
            onLoadAvailability:false,
            firstLoad:true,
            i18n:[]
        },
        watch:{
            extra_price:{
                handler:function f() {
                    this.step = 1;
                    // this.handleTotalPrice();
                },
                deep:true
            },
            start_date(){
                this.step = 1;
            },
            guests(){
                this.step = 1;
            },
            person_types:{
                handler:function f() {
                    this.step = 1;
                },
                deep:true
            },
        },
        computed:{
            total_price:function(){
                var me = this;
                if (me.start_date !== "" && me.total_rooms > 0) {
                    var guests = me.children + me.adults;
                    var total_price = 0;
                    _.forEach(this.rooms,function (item) {
                        if(item.number_selected){
                            total_price += item.price* parseInt(item.number_selected);
                        }
                    });
                    for (var ix in me.buyer_fees) {
                        var item = me.buyer_fees[ix];
                        var type_total = 0;
                        type_total += parseFloat(item.price);
                        if (typeof item.per_person !== "undefined") {
                            type_total = type_total * guests;
                        }
                        total_price += type_total;
                    }
                    return total_price;
                }
                return 0;
            },
            total_rooms:function(){
                var me = this;
                if (me.start_date !== "") {
                    var t = 0;
                    _.forEach(this.rooms,function (item) {
                        if(item.number_selected){
                            t += parseInt(item.number_selected);
                        }
                    })
                    return t;
                }
                return 0;
            },
            total_price_html:function(){
                if(!this.total_price) return '';
                setTimeout(function () {
                    $('[data-toggle="tooltip"]').tooltip();
                    $(document).trigger("scroll");
                },200);
                return window.bravo_format_money(this.total_price);
            },
            daysOfWeekDisabled(){
                var res = [];

                for(var k in this.open_hours)
                {
                    if(typeof this.open_hours[k].enable == 'undefined' || this.open_hours[k].enable !=1 ){

                        if(k == 7){
                            res.push(0);
                        }else{
                            res.push(k);
                        }
                    }
                }

                return res;
            },
            guests(){
                return this.children + this.adults;
            }
        },
        created:function(){
            for(var k in bravo_booking_data){
                this[k] = bravo_booking_data[k];
            }
            this.checkAvailability();
        },
        mounted(){
            var me = this;
            /*$(".hotel_room_book_status").sticky({
                topSpacing:30,
                bottomSpacing:$(document).height() - $('.end_tour_sticky').offset().top + 40
            });*/


            var options = {
                // singleDatePicker: true,
				maxSpan: {
					"days": 30
				},
                showCalendar: false,
                sameDate: true,
                autoApply           : true,
                disabledPast        : true,
                dateFormat          : bookingCore.date_format,
                enableLoading       : true,
                showEventTooltip    : true,
                classNotAvailable   : ['disabled', 'off'],
                disableHightLight: true,
                minDate:this.minDate,
                // opens:'left',
                isInvalidDate:function (date) {
                    for(var k = 0 ; k < me.allEvents.length ; k++){
                        var item = me.allEvents[k];
                        if(item.start == date.format('YYYY-MM-DD')){
                            return item.active ? false : true;
                        }
                    }
                    return false;
                }
            };


            this.$nextTick(function () {
                $(this.$refs.start_date).daterangepicker(options).on('apply.daterangepicker',
                    function (ev, picker) {
                        if(picker.endDate.diff(picker.startDate,'day') <=0){
							picker.endDate.add(1,'day');
                        }
                        me.start_date = picker.startDate.format('YYYY-MM-DD');
                        me.end_date = picker.endDate.format('YYYY-MM-DD');
                        me.start_date_html = picker.startDate.format(bookingCore.date_format) +' <i class="fa fa-long-arrow-right" style="font-size: inherit"></i> '+ picker.endDate.format(bookingCore.date_format);
                        // me.handleTotalPrice();
                    })
            })
        },
        methods:{
            handleTotalPrice:function() {
            },
            formatMoney: function (m) {
                return window.bravo_format_money(m);
            },
            validate(){
                if(!this.start_date || !this.end_date)
                {
					this.message.status = false;
                    this.message.content = bravo_booking_i18n.no_date_select;
                    return false;
                }
                if(!this.guests )
                {
					this.message.status = false;
                    this.message.content = bravo_booking_i18n.no_guest_select;
                    return false;
                }

                return true;
            },
            addPersonType(type){
                switch (type){
                    case "adults":
                        this.adults ++ ;
                    break;
                    case "children":
                        this.children ++;
                    break;
                }
                // this.handleTotalPrice();
            },
            minusPersonType(type){
				switch (type){
					case "adults":
						if(this.adults  >=2){
						    this.adults --;
                        }
						break;
					case "children":
						if(this.children  >=1){
							this.children --;
						}
						break;
				}
                // this.handleTotalPrice();
            },
			checkAvailability:function () {
                var me  = this;
                this.onLoadAvailability = true;
                if(!this.firstLoad){
                    if(!this.start_date || !this.start_date){
                        bookingCoreApp.showError(this.i18n.date_required);
                    }
                }

                $.ajax({
                    url:bookingCore.url+'/hotel/checkAvailability',
                    data:{
                        hotel_id:this.id,
                        start_date:this.start_date,
                        end_date:this.end_date,
						firstLoad:me.firstLoad,
                        adults:this.adults,
                        children:this.children,
                    },
                    method:'post',
                    success:function (json) {
                        me.onLoadAvailability = false;
                        me.firstLoad = false;
                        if(json.rooms){
                            me.rooms = json.rooms;
                            me.$nextTick(function () {
                                me.initJs();
                            })
                        }
                        if(json.message){
                            bookingCoreApp.showAjaxMessage(json);
                        }
                    },
                    error:function (e) {
                        me.firstLoad = false;
                        bookingCoreApp.showAjaxError(e);
                    }
                })
			},
            doSubmit:function (e) {
                e.preventDefault();
                if(this.onSubmit) return false;

                if(!this.validate()) return false;

                this.onSubmit = true;
                var me = this;

                this.message.content = '';

                if(this.step == 1){
                    this.html = '';
                }

                $.ajax({
                    url:bookingCore.url+'/booking/addToCart',
                    data:{
                        service_id:this.id,
                        service_type:"hotel",
                        start_date:this.start_date,
                        end_date:this.end_date,
                        // person_types:this.person_types,
                        extra_price:this.extra_price,
                        // step:this.step,
                        adults:this.adults,
                        children:this.children,
                        rooms:_.map(this.rooms,function (item) {
                            return _.pick(item,['id','number_selected'])
                        })
                    },
                    dataType:'json',
                    type:'post',
                    success:function(res){

                        if(!res.status){
                            me.onSubmit = false;
                        }
                        if(res.message){
                            bookingCoreApp.showAjaxMessage(res);
                        }

                        if(res.step){
                            me.step = res.step;
                        }
                        if(res.html){
                            me.html = res.html
                        }

                        if(res.url){
                            window.location.href = res.url
                        }

                        if(res.errors && typeof res.errors == 'object')
                        {
                            var html = '';
                            for(var i in res.errors){
                                html += res.errors[i]+'<br>';
                            }
                            me.message.content = html;

                            bookingCoreApp.showError(html);
                        }
                    },
                    error:function (e) {
                        console.log(e);
                        me.onSubmit = false;

                        bravo_handle_error_response(e);

                        if(e.status == 401){
                            $('.bravo_space_book_wrap').modal('hide');
                        }

                        if(e.status != 401 && e.responseJSON){
                            me.message.content = e.responseJSON.message ? e.responseJSON.message : 'Can not booking';
                            me.message.type = false;

                        }
                    }
                })
            },
            openStartDate:function(){
                $(this.$refs.start_date).trigger('click');
            },
            initJs:function () {
                //$('.fotorama').fotorama();
            },
            showGallery:function(e,id,gallery)
            {
                if(gallery !== null){
                    var p  = $(e.target).closest('.row');
                    $('#modal_room_'+id).modal().modal('show');
                    p.find('.fotorama').each(function () {
                        $(this).fotorama();
                    });
                }
            }
        }

    });

    $('.bravo-video-popup').click(function() {
        let video_url = $(this).data( "src" );
        let target = $(this).data( "target" );
        $(target).find(".bravo_embed_video").attr('src',video_url + "?autoplay=0&amp;modestbranding=1&amp;showinfo=0" );
    });


    $(window).on("load", function () {
        var urlHash = window.location.href.split("#")[1];
        if (urlHash &&  $('.' + urlHash).length ){
            var offset_other = 70
            if(urlHash === "review-list"){
                offset_other = 330;
            }
            $('html,body').animate({
                scrollTop: $('.' + urlHash).offset().top - offset_other
            }, 1000);
        }
    });

    $(".bravo-button-book-mobile").click(function () {
        //$('.bravo_single_book_wrap').modal('show');

    });

    $(".bravo_detail_space .g-faq .item .header").click(function () {
        $(this).parent().toggleClass("active");
    });

    $(".btn-show-all").click(function () {
        $(this).parent().find(".d-none").removeClass("d-none");
        $(this).addClass("d-none");
    });

    $(".start_room_sticky").each(function () {
        var $this_list_room = $(this).closest(".hotel_rooms_form");
        $(window).scroll(function() {
            var window_height = $(window).height();
            var windowTop = $(window).scrollTop();
            var stickyTop = $('.start_room_sticky').offset().top + 100 - window_height;
            var stickyBottom =  stickyTop + $this_list_room.height() - 300;
            if (stickyTop < windowTop && windowTop < stickyBottom) {
                $(document).find(".hotel_room_book_status").addClass("sticky").css("width",$this_list_room.width());
                $(document).find(".end_room_sticky").css("min-height",$(document).find(".hotel_room_book_status").height() + 32 + 20);

                setTimeout(function () {
                    $(document).find(".hotel_room_book_status").addClass("active");
                },100);
            } else {
                $(document).find(".hotel_room_book_status").removeClass("sticky").css("width","auto");
                $(document).find(".end_room_sticky").css("min-height","auto");
                $(document).find(".hotel_room_book_status").removeClass("active");
            }
        });
    });

})(jQuery);
