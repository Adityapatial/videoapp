var themeManager = (function () {
    'use strict';

    function addRule(stylesheetId, selector, rule) {
        var stylesheet = document.getElementById(stylesheetId);

        if (stylesheet) {
            stylesheet = stylesheet.sheet;
            if (stylesheet.addRule) {
                stylesheet.addRule(selector, rule);
            } else if (stylesheet.insertRule) {
                stylesheet.insertRule(selector + ' { ' + rule + ' }', stylesheet.cssRules.length);
            }
        }
    }

    function onAppResize(event){

        var styleId = "hostStyle";
        var info = document.querySelector('.info').clientWidth;
        addRule(styleId, "main .info", "height:" + (window.innerHeight - 80) + 'px');

        var images = document.querySelectorAll('.flex-images .item');


        for (var i = 0; i < images.length; i++) {
            if (document.querySelector('.info').clientWidth >= 436) {
                images[i].getElementsByTagName('img')[0].style.height = (((info / 2) - 10) * parseInt(images[i].dataset.h)) / parseInt(images[i].dataset.w) + 'px';
            } else {
                images[i].getElementsByTagName('img')[0].style.height = ((info - 10) * parseInt(images[i].dataset.h)) / parseInt(images[i].dataset.w) + 'px';
            }
        }

        if(images.length >= 1) {
            console.log(images[0].style.height, document.querySelector('.info').clientWidth);
        }

    }



    function init() {

        var csInterface = new CSInterface();
        onAppResize();
        window.addEventListener('resize', onAppResize);
    }

    return {
        init: init
    };


}());
