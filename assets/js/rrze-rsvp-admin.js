"use strict";jQuery(document).ready(function(r){rrze_rsvp_admin.dateformat;var o=rrze_rsvp_admin.text_cancel,t=rrze_rsvp_admin.text_cancelled,i=rrze_rsvp_admin.text_confirmed,a=rrze_rsvp_admin.ajaxurl;function n(r,o){var e=o.attr("data-id"),n=o.attr("href");jQuery.ajax({type:"POST",url:a,data:{action:"booking_action",id:e,type:r}}).fail(function(e){console.error("AJAX request failed")}).done(function(e){e=JSON.parse(e),jQuery.ajax({type:"GET",url:n}).fail(function(e){console.error("AJAX request failed")}).done(function(e){"confirm"==r?o.addClass("rrze-rsvp-confirmed").attr("disabled","disabled").html(i):o.attr("disabled","disabled").html(t)})})}r(".rrze-rsvp-confirm").click(function(e){return e.preventDefault(),n("confirm",jQuery(this)),!1}),r(".rrze-rsvp-cancel").click(function(e){return e.preventDefault(),confirm(o)&&n("cancel",jQuery(this)),!1});var e=jQuery("div#rrze-rsvp-booking-details"),s=e.find("select#rrze-rsvp-booking-seat"),c=e.find("input#rrze-rsvp-booking-start_date"),d=e.find("input#rrze-rsvp-booking-start_time"),l=e.find("input#rrze-rsvp-booking-end_date"),v=e.find("input#rrze-rsvp-booking-end_time");function p(e){"reservation"==r("select#rrze-rsvp-room-bookingmode option:checked").val()||"consultation"==r("select#rrze-rsvp-room-bookingmode option:checked").val()?r("div#rrze-rsvp-additionals").slideDown(e):r("div#rrze-rsvp-additionals").slideUp(e),"reservation"==r("select#rrze-rsvp-room-bookingmode option:checked").val()&&r("div#rrze-rsvp-consultation").slideDown(e),"consultation"==r("select#rrze-rsvp-room-bookingmode option:checked").val()&&r("div#rrze-rsvp-consultation").slideUp(e)}function u(e){r("#rrze-rsvp-room-auto-confirmation").is(":checked")?r("div.cmb2-id-rrze-rsvp-room-instant-check-in").slideDown(e):(r("div.cmb2-id-rrze-rsvp-room-instant-check-in").slideUp(e),r("#rrze-rsvp-room-instant-check-in").is(":checked")&&r("#rrze-rsvp-room-instant-check-in").prop("checked",!1))}d.attr("readonly","readonly"),s.change(function(){c.val(""),d.val(""),l.val(""),v.val(""),jQuery("div.select_timeslot_container").remove()}),c.change(function(){l.val(c.val()),d.val(""),v.val(""),""==s.val()||""==c.val()?alert(rrze_rsvp_admin.alert_no_seat_date):(jQuery("div.select_timeslot_container").remove(),jQuery.post(a,{action:"ShowTimeslots",seat:s.val(),date:c.val()},function(e){0!=e&&jQuery("input#rrze-rsvp-booking-start_time").after(e)}))}),r("div.cmb2-id-rrze-rsvp-booking-start").on("change","select.select_timeslot",function(){var e=jQuery(this).val(),r=jQuery(this).find(":selected").data("end");console.log(e),console.log(r),d.val(e),v.val(r)}),p(0),u(0),r("select#rrze-rsvp-room-bookingmode").on("change",function(){p(100)}),r("#rrze-rsvp-room-auto-confirmation").click(function(){u(100)})});