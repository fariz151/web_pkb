/**@license showplusx: a performance-optimized slideshow engine
 * @author  Levente Hunyadi
 * @version 1.0
 * @remarks Copyright (C) 2009-2017 Levente Hunyadi
 * @remarks Licensed under GNU/GPLv3, see https://www.gnu.org/licenses/gpl-3.0.html
 * @see     https://hunyadi.info.hu/projects/showplusx
 **/

/*
* showplusx: a performance-optimized slideshow engine
* Copyright 2009-2017 Levente Hunyadi
*
* showplusx is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* showplusx is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with showplusx.  If not, see <https://www.gnu.org/licenses/>.
*/

'use strict';

/** @enum {string} */
const ShowPlusXOrder = {
    /** The same order as specified. */
    NORMAL: 'normal',
    /** The opposite order than specified. */
    REVERSE: 'reverse',
    /** The next item is always chosen randomly. */
    RANDOM: 'random'
};

/** @enum {string} */
const ShowPlusXPosition = {
    /** Not visible. */
    NONE: 'hidden',
    /** Position at the top of the container. */
    TOP: 'top',
    /** Position at the bottom of the container. */
    BOTTOM: 'bottom',
    /** Position at the start of the reading order (i.e. left for LTR, right for RTL languages). */
    START: 'start',
    /** Position at the end of the reading order (i.e. right for LTR, left for RTL languages). */
    END: 'end'
};

/**
* Attributes for an item.
* The object has the following properties:
* + src: URL pointing to the image to display (either relative or absolute).
* + caption: Caption position within slideshow view-port ('top'|'bottom').
* + title: Caption text associated with the image. May contain HTML tags.
* + href: URL or URL fragment that the item points to.
* + target: Specifies where to display the linked URL (e.g. '_self' or '_blank').
*
* @typedef {{
*     src: string,
*     thumbsrc: string,
*     caption: !ShowPlusXPosition,
*     title: string,
*     href: string,
*     target: string
* }}
*/
const ShowPlusXItemProperties = {
    'src': '',
    'thumbsrc': '',
    'caption': ShowPlusXPosition.NONE,
    'title': '',
    'href': '',
    'target': '_blank'
};

/**
* @param {!Object<string,!Array<string>>} styles
* @return {!Array<string>}
*/
function getAnimationStyleArray(styles) {
    let styleArray = [];
    Object.keys(styles).forEach(function (style) {
        let variants = styles[style];
        variants.forEach(function (variant) {
            styleArray.push(style + '-' + variant);
        });
    });
    return styleArray;
}

/**
* @const
* @type {!Object<string,!Array<string>>}
*/
const animationStyles = {
    'fade':['fade'],
    'fold':['left','right'],
    'push':['left','right','top','bottom'],
    'circle':['circle'],
    'kenburns':['topleft','topright','bottomright','bottomleft'],
};
/**
* @const
* @type {!Array<string>}
*/
const animationStyleArray = getAnimationStyleArray(animationStyles);

/**
* Options for the slideshow.
* The object has the following properties:
* + items: The list of items that comprise the slideshow.
* + effects: The list of transition animation effects to choose from.
* + defaults: Default attributes for items.
* + dir: Text directionality (ltr|rtl).
*
* @typedef {{
*     items: !Array<!ShowPlusXItemProperties>,
*     order: !ShowPlusXOrder,
*     effects: !Array<string>,
*     defaults: !ShowPlusXItemProperties,
*     dir: string
* }}
*/
const ShowPlusXOptions = {
    'items': [],
    'order': ShowPlusXOrder.NORMAL,
    'effects': animationStyleArray,
    'defaults': ShowPlusXItemProperties,
    'navigation': ShowPlusXPosition.NONE,
    'dir': 'ltr'
};

/**
* Returns a random integer between min (inclusive) and max (inclusive).
* The integers returned are uniformly distributed.
*
* @param {number} min The smallest possible integer value returned (inclusive).
* @param {number} max The greatest possible integer value returned (inclusive).
* @return {number}
*/
function getRandomInt(min, max) {
    // use Math.floor(), not Math.round(), the latter would give a non-uniform distribution
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
* Returns a random integer in a range not including a specified integer.
* The integers returned are uniformly distributed.
*
* @param {number} min The smallest possible integer value returned (inclusive).
* @param {number} max The greatest possible integer value returned (inclusive).
* @param {number} excl The integer value never to be returned.
* @return {number}
*/
function getRandomIntExcluding(min, max, excl) {
    const r = getRandomInt(min, max - 1);
    return (r >= excl) ? r + 1 : r;
}

/**
* Sets all undefined properties on an object using a reference object.
* @param {Object|null|undefined} obj
* @param {!Object} ref
* @return {!Object}
*/
function applyDefaults(obj, ref) {
    /** @type {!Object} */
    let extended = obj || {};
    for (const prop in JSON.parse(JSON.stringify(ref))) {  // use JSON functions to clone object
        extended[prop] = /** @type {*} */ (extended[prop]) || /** @type {*} */ (ref[prop]);
    }
    return extended;
}

/**
* Returns the index of the specified node in the collection of its sibling nodes.
* @param {Element} node
* @return {number}
*/
function getElementIndex(node) {
    return Array.prototype.indexOf.call(node.parentNode.childNodes, node);
}

/**
* @param {!Element} elem
* @param {boolean} visible
*/
function toggleVisibility(elem, visible) {
    let classList = elem.classList;
    if (visible) {
        classList.remove('showplusx-hidden');
    } else {
        classList.add('showplusx-hidden');
    }
}

/**
* @param {!Element} elem
* @param {!Array<string>} enableAnimationClasses
* @param {!Array<string>} disableAnimationClasses
*/
function toggleAnimation(elem, enableAnimationClasses, disableAnimationClasses) {
    let classList = elem.classList;
    disableAnimationClasses.forEach(function (item) {
        classList.remove('showplusx-animation-' + item);
    });
    enableAnimationClasses.forEach(function (item) {
        classList.add('showplusx-animation-' + item);
    });
}

/** @constructor */
function ShowPlusXCacheEntry() { }
/** @type {?string} */
ShowPlusXCacheEntry.prototype.url = null;
/** @type {number} */
ShowPlusXCacheEntry.prototype.width = 0;
/** @type {number} */
ShowPlusXCacheEntry.prototype.height = 0;

/**
* @constructor
* @param {Element} parent The container element in which to inject the slideshow.
* @param {ShowPlusXOptions=} options Settings that customize the appearance and behavior of the slideshow.
*/
function ShowPlusXSlideshow(parent, options) {
    this.options = /** @type {!ShowPlusXOptions} */ (applyDefaults(options, ShowPlusXOptions));
    this.options['defaults'] = /** @type {!ShowPlusXItemProperties} */ (applyDefaults(this.options['defaults'], ShowPlusXItemProperties));

    let self = this;
    /**
    * @dict
    * @type {!Object<string,ShowPlusXCacheEntry>}
    */
    this.cache = {};
    /** @type {number} */
    this.current = 0;

    let container = document.createElement('div');
    container.classList.add('showplusx-slideshow');
    container.dir = this.options['dir'];
    /** @type {Element} */
    this.container = container;

    let viewport = document.createElement('div');
    viewport.classList.add('showplusx-viewport');
    this.viewport = viewport;

    // captions
    let caption = document.createElement('div');
    caption.classList.add('showplusx-caption');
    caption.addEventListener('animationend', function (event) {
        toggleAnimation(caption, [], ['in']);
    });
    /** @type {Element} */
    this.caption = caption;

    // quick-access navigation
    /** @type {Element} */
    let navigation = document.createElement('div');
    navigation.classList.add('showplusx-navigation');
    navigation.classList.add('showplusx-' + /** @type {string} */ (this.options['navigation']));

    let items = /** @type {!Array<!ShowPlusXItemProperties>} */ (this.options['items']);
    const itemcount = items.length;
    if (itemcount > 0) {
        items.forEach(function (item, index) {
            let elem = document.createElement('a');
            let classList = elem.classList;
            classList.add('showplusx-item');
            classList.add('showplusx-hidden');
            elem.addEventListener('animationend', function (event) {
                if (classList.contains('showplusx-animation-in')) {
                    toggleAnimation(elem, ['show'], ['in']);
                } else if (classList.contains('showplusx-animation-show')) {
                    toggleAnimation(elem, ['out'], ['show']);
                    toggleVisibility(caption, false);

                    // check if the item for which the display animation has ended is the current item;
                    // if so, advance to the next item
                    if (classList.contains('showplusx-current')) {
                        switch (self.options['order']) {
                            case ShowPlusXOrder.NORMAL:
                                self.next();
                                break;
                            case ShowPlusXOrder.REVERSE:
                                self.previous();
                                break;
                            case ShowPlusXOrder.RANDOM:
                                self.display(getRandomIntExcluding(0, itemcount - 1, index));
                                break;
                        }
                    }
                } else if (classList.contains('showplusx-animation-out')) {
                    toggleAnimation(elem, [], ['out']);
                    toggleVisibility(elem, false);
                }
            }, false);
            viewport.appendChild(elem);

            let navelem = document.createElement('span');
            let url = item['thumbsrc'] || item['src'];
            navelem.style.backgroundImage = 'url("' + url.replace(/[\n\r\f]/g, '').replace(/(["\\])/g, '\\$1') + '")';
            navelem.addEventListener('click', function (event) {
                self.display(index);
            }, false);
            navigation.appendChild(navelem);
        });
        this.first();
    }
    container.appendChild(viewport);

    // previous and next navigation arrows
    let previous = document.createElement('div');
    previous.classList.add('showplusx-previous');
    previous.addEventListener('click', function (event) {
        self.previous();
    }, false);

    let next = document.createElement('div');
    next.classList.add('showplusx-next');
    next.addEventListener('click', function (event) {
        self.next();
    }, false);

    container.appendChild(previous);
    container.appendChild(next);

    container.appendChild(caption);
    container.appendChild(navigation);

    parent.appendChild(container);
}
window['ShowPlusXSlideshow'] = ShowPlusXSlideshow;
ShowPlusXSlideshow.prototype.destroy = function () {
    let container = this.container;
    container.parentNode.removeChild(container);
    this.container = null;
}
ShowPlusXSlideshow.prototype['destroy'] = ShowPlusXSlideshow.prototype.destroy;
/**
* @return {number}
*/
ShowPlusXSlideshow.prototype.getPrevious = function () {
    let items = /** @type {!Array<!ShowPlusXItemProperties>} */ (this.options['items']);
    let upcoming = this.current - 1;
    if (upcoming < 0) {
        upcoming = items.length - 1;
    }
    return upcoming;
};
/**
* @return {number}
*/
ShowPlusXSlideshow.prototype.getNext = function () {
    let items = /** @type {!Array<!ShowPlusXItemProperties>} */ (this.options['items']);
    let upcoming = this.current + 1;
    if (upcoming >= items.length) {
        upcoming = 0;
    }
    return upcoming;
};
ShowPlusXSlideshow.prototype.first = function () {
    this.display(0);
};
ShowPlusXSlideshow.prototype['first'] = ShowPlusXSlideshow.prototype.first;
ShowPlusXSlideshow.prototype.previous = function () {
    this.display(this.getPrevious());
};
ShowPlusXSlideshow.prototype['previous'] = ShowPlusXSlideshow.prototype.previous;
ShowPlusXSlideshow.prototype.next = function () {
    this.display(this.getNext());
};
ShowPlusXSlideshow.prototype['next'] = ShowPlusXSlideshow.prototype.next;
/**
* @param {number} index
*/
ShowPlusXSlideshow.prototype.display = function (index) {
    // prevent the slideshow from automatically advancing at the end of the animation sequence
    this.viewport.children[this.current].classList.remove('showplusx-current');

    // make sure the image has been loaded before we attempt to display it
    this.load(index, this.show.bind(this, index));
};
ShowPlusXSlideshow.prototype['display'] = ShowPlusXSlideshow.prototype.display;
/**
* @param {number} index
*/
ShowPlusXSlideshow.prototype.show = function (index) {
    // pre-fetch neighboring images
    this.load(this.getPrevious());  // pre-fetch next image
    this.load(this.getNext());  // pre-fetch next image

    let classList = this.viewport.classList;

    /**
    * @param {string} effect
    * @param {boolean} toggle
    */
    function toggleEffect(effect, toggle) {
        const category = effect.substr(0, effect.indexOf('-'));
        if (toggle) {
            if (category) {
                classList.add('showplusx-' + category);  // general animation theme, e.g. "kenburns"
            }
            classList.add('showplusx-' + effect);  // specific animation style, e.g. "kenburns-topleft"
        } else {
            if (category) {
                classList.remove('showplusx-' + category);  // general animation theme, e.g. "kenburns"
            }
            classList.remove('showplusx-' + effect);  // specific animation style, e.g. "kenburns-topleft"
        }
    }

    // remove all animation effect styles and variants previously applied to the element
    const effects = /** @type {!Array<string>} */ (this.options['effects']);
    effects.forEach(function (effect) {
        toggleEffect(effect, false);
    });

    // add desired animation style and variant
    const effect = effects[getRandomInt(0, effects.length - 1)];
    if (effect) {
        toggleEffect(effect, true);
    }

    // update image
    let active = /** @type {HTMLAnchorElement} */ (this.viewport.children[index]);
    let item = /** @type {!ShowPlusXItemProperties} */ (applyDefaults(this.options['items'][index], this.options['defaults']));
    const url = this.cache[item['src']].url;
    if (item['href']) {
        active.href = item['href'];
    } else {
        active.removeAttribute('href');
    }
    if (item['target']) {
        active.target = item['target'];
    } else {
        active.removeAttribute('target');
    }
    active.style.setProperty('background-image', 'url("' + url + '")');

    let caption = this.caption;
    caption.innerHTML = item['title'];
    caption.classList.remove('showplusx-caption-' + ShowPlusXPosition.TOP);
    caption.classList.remove('showplusx-caption-' + ShowPlusXPosition.BOTTOM);
    caption.classList.add('showplusx-caption-' + /** @type {string} */ (item['caption']));

    // activate automatic advancing when slideshow animation sequence ends
    this.current = index;
    active.classList.add('showplusx-current');

    // kick off animation
    toggleAnimation(active, ['in'], ['out','show']);
    toggleAnimation(caption, ['in'], []);
    toggleVisibility(active, true);
    toggleVisibility(caption, true);
};
/**
* @param {number} index
* @param {function(ShowPlusXCacheEntry)=} callback
*/
ShowPlusXSlideshow.prototype.load = function (index, callback) {
    let handler = callback || new Function();
    const src = /** @type {string} */ (this.options['items'][index]['src']);
    if (this.cache.hasOwnProperty(src)) {  // look up using possibly relative URL
        handler(this.cache[src]);
    } else {
        let self = this;
        let data = new ShowPlusXCacheEntry();

        let image = /** @type {!HTMLImageElement} */ (document.createElement('img'));
        image.addEventListener('load', function (event) {
            // window.setTimeout(function () {
            data.url = image.src;  // fully expanded URL
            data.width = image.width;  // natural width
            data.height = image.height;  // natural height
            self.cache[src] = data;  // save using possibly relative URL
            handler(data);
            // }, 3000);
        }, false);
        image.addEventListener('error', function (event) {
            self.cache[src] = data;  // save on error to avoid fetching missing images repeatedly
            handler(data);
        }, false);
        image.src = src;
    }
};
