jQuery(function(){
	// swaps the mobile menu from parent to child
	jQuery("#main-menu ul.sub-menu").prepend("<li class='submenu-back'><button class='submenu-backbtn'>Back</button></li>");

	jQuery(document).on("click", ".menu-item.menu-item-has-children a", function(e){
		let subMenuUl = jQuery(this).siblings(".children-sub-menu-over");
		if ( subMenuUl.length > 0 ) {
			e.preventDefault();
			jQuery(this).siblings(".children-sub-menu-over").addClass("show");
		}
		return true;
	});	

	jQuery(document).on("click", ".submenu-back", function(e){
		console.log( jQuery(this).closest(".sub-menu").length );
		jQuery(this).closest(".sub-menu").removeClass('show');
	});	
});