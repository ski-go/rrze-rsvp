"use strict";jQuery(document).ready((function(){jQuery(".rrze_rsvp_datepicker").each((function(e,r){var t=jQuery(r);t.datepicker({altField:"#"+t.attr("data-target"),altFormat:"yy-mm-dd"})})),jQuery(".rrze_rsvp_datepicker[name=exception_start_datepicker]").change((function(){jQuery("#rrze_rsvp_exception_end").val()||(jQuery("#rrze_rsvp_exception_end").val(jQuery("#rrze_rsvp_exception_start").val()),jQuery(".rrze_rsvp_datepicker[name=rrze_rsvp_exception_end_datepicker]").val(jQuery(".rrze_rsvp_datepicker[name=exception_start_datepicker]").val()))})),jQuery(".exception_hide").hide(),jQuery("#rrze_rsvp_exception_allday").click((function(){jQuery(".exception_hide").toggle()}))}));