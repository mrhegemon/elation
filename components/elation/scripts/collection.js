elation.require([], function() {
  /** 
   * Simple data collection
   *
   * @class simple
   * @augments elation.component.base
   * @memberof elation.collection
   * @alias elation.collection.simple
   *
   * @param {object} args
   */
  elation.component.add("collection.simple", function() {
    this.init = function() {
      this.items = [];
      this.allowduplicates = false;

      Object.defineProperty(this, "length", { get: function() { return this.getlength(); } });
    }

    /**
     * Add an item, optionally at a specified position
     * @function add
     * @memberof elation.collection.simple#
     * @param {object} item
     * @param {integer} pos
     * @returns {boolean}
     * @emits collection_add
     */
    this.add = function(item, pos) {
      if (this.allowduplicates || !this.contains(item)) {
        if (pos == undefined || pos >= this.items.length) {
          this.items.push(item);
        } else {
          this.items.splice(pos, 0, item);
        }
        elation.events.fire({type: 'collection_add', data: {item: item}, itemcount: this.items.length});
        return true;
      }
      return false;
    }
    /**
     * Remove an item
     * @function remove
     * @memberof elation.collection.simple#
     * @param {object} item
     * @returns {boolean}
     * @emits collection_remove
     */
    this.remove = function(item) {
      var idx = this.find(item);
      if (idx != -1) {
        this.items.splice(idx, 1);
        elation.events.fire({type: 'collection_remove', data: {item: item}, itemcount: this.items.length});
        return true;
      }
      return false;
    }
    /**
     * Move an item to a new position
     * @function move
     * @memberof elation.collection.simple#
     * @param {object} item
     * @param {integer} pos
     * @returns {boolean}
     * @emits collection_move
     */
    this.move = function(item, pos) {
      var idx = this.items.indexOf(item);
      if (idx != -1 && idx != pos) {
        this.items.splice(idx, 1);
        this.items.splice(pos, 0, item);
        elation.events.fire({type: 'collection_move', data: {item: item, from: idx, to: pos}, itemcount: this.items.length});
        return true;
      }
      return false;
    }
    /**
     * Return the item index of the specified item
     * @function find
     * @memberof elation.collection.simple#
     * @param {object} item
     * @returns {integer}
     */
    this.find = function(item) {
      return this.items.indexOf(item);
    }
    /**
     * Check whether the specified item exists in this dataset
     * @function contains
     * @memberof elation.collection.simple#
     * @param {object} item
     * @returns {boolean}
     */
    this.contains = function(item) {
      return this.find(item) != -1;
    }
    /**
     * Get a reference to the specified item
     * @function get
     * @memberof elation.collection.simple#
     * @returns {object}
     */
    this.get = function(item) {
      var idx = this.find(item);
      if (idx != -1) {
        return this.items[idx];
      }
      return null;
    }
    /**
     * Returns the number of items contained in this collection
     * @function getlength
     * @memberof elation.collection.simple#
     * @returns {integer}
     */
    this.getlength = function() {
      return this.items.length;
    }
    /**
     * Clear all items from the list
     * @function clear
     * @memberof elation.collection.simple#
     * @returns {boolean}
     * @emits collection_clear
     */
    this.clear = function() {
      this.items.splice(0, this.items.length);
      elation.events.fire({type: "collection_clear", element: this});
    }
    this.filter = function(filterfunc, filterargs) {
      return elation.collection.filter({parent: this, filterfunc: filterfunc, filterargs: filterargs});
    }
  });
  /** 
   * Indexed data collection
   * Uses the specified index parameter to enforce uniqueness
   *
   * @class indexed
   * @augments elation.collection.simple
   * @memberof elation.collection
   * @alias elation.collection.indexed
   *
   * @param {object} args
   * @param {string} args.index
   * @param {string} args.indextransform
   */
  elation.component.add("collection.indexed", function() {
    this.init = function() {
      elation.collection.simple.base.prototype.init.call(this);
      this.index = this.args.index;
      this.indextransform = this.args.indextransform || false;
      this.itemindex = {};
    }
    this.add = function(item, pos) {
      var idx = this.getindex(item);
      if (!(idx in this.itemindex)) {
        this.itemindex[idx] = item;
        return elation.collection.simple.base.prototype.add.call(this, item, pos);
      } else if (!elation.utils.isNull(pos)) {
        var realitem = this.itemindex[idx];
        if (this.items[pos] != realitem) {
          this.move(realitem, pos);
        }
      }
      return false;
    }
    this.remove = function(item) {
      var idx = this.getindex(item);
      if (idx in this.itemindex) {
        var realitem = this.itemindex[idx];
        delete this.itemindex[idx];
        return elation.collection.simple.base.prototype.remove.call(this, realitem);
      }
      return false;
    }
    this.find = function(item) {
      var idx = this.getindex(item);
      if (!elation.utils.isNull(this.itemindex[idx])) {
        return elation.collection.simple.base.prototype.find.call(this, this.itemindex[idx]);
      }
      return elation.collection.simple.base.prototype.find.call(this, item);
    }
    this.getlength = function() {
      return Object.keys(this.itemindex).length;
    }
    this.getindex = function(idx) {
      if (!elation.utils.isString(idx)) {
        idx = idx[this.index];
      }
      if (this.indextransform) {
        idx = this.indextransform(idx);
      }
      return idx;
    }
  }, elation.collection.simple);

  /**
   * localStorage-backed indexed collection
   * Auto-save changes to localStorage, loads on init.
   * 
   * @class localindexed
   * @augments elation.collection.indexed
   * @memberof elation.collection
   *
   * @alias elation.collection.indexed
   * @param {object} args
   * @param {string} args.index
   * @param {string} args.storagekey
   */
  elation.component.add("collection.localindexed", function() {
    this.init = function() {
      elation.collection.indexed.base.prototype.init.call(this);
      this.storagekey = this.args.storagekey;
      if (!elation.utils.isEmpty(this.storagekey)) {
        this.load(this.storagekey);
      }
    }
    this.add = function(item, pos) {
      var changed = elation.collection.indexed.base.prototype.add.call(this, item, pos);
      if (changed) {
        this.save();
      }
    }
    this.move = function(item, pos) {
      var changed = elation.collection.indexed.base.prototype.move.call(this, item, pos);
      if (changed) {
        this.save();
      }
    }
    this.remove = function(item) {
      var changed = elation.collection.indexed.base.prototype.remove.call(this, item);
      if (changed) {
        this.save();
      }
    }
    this.save = function(key) {
      if (!key) key = this.storagekey;
      try {
        localStorage[this.storagekey] = JSON.stringify(this.items);
        elation.events.fire({type: "collection_save", element: this});
        return true;
      } catch (e) {
        console.error(e.stack);
      }
      return false;
    }
    this.load = function(key) {
      if (!key) key = this.storagekey;
      if (!elation.utils.isEmpty(localStorage[this.storagekey])) {
        try {
          elation.events.fire({type: "collection_load_begin", element: this});
          this.items = JSON.parse(localStorage[this.storagekey]);
          this.buildindex();
          elation.events.fire({type: "collection_load", element: this});
          return true;
        } catch (e) {
          console.error(e.stack);
        }
      }
      return false;
    }
    this.buildindex = function() {
      for (var i = 0; i < this.items.length; i++) {
        var idx = this.getindex(this.items[i]);
        this.itemindex[idx] = this.items[i];
      }
    }
  }, elation.collection.indexed);

  /** 
   * API-backed data collection
   * Provides a collection interface to a REST API
   *
   * @class api
   * @augments elation.collection.simple
   * @memberof elation.collection
   * @alias elation.collection.api
   *
   * @param {object} args
   * @param {string} args.host
   * @param {string} args.endpoint
   * @param {object} args.apiargs
   * @param {object} args.datatransform
   * @param {function} args.datatransform.items
   * @param {function} args.datatransform.count
   */
  elation.component.add("collection.api", function() {
    this.init = function() {
      elation.collection.simple.base.prototype.init.call(this);
      this.host = this.args.host || '';
      this.endpoint = this.args.endpoint;
      this.apiargs = this.args.apiargs;
      this.datatransform = this.args.datatransform || {};
      //this.data = { items: [], count: 0 };
      Object.defineProperties(this, {
        items: { get: this.getitems }
      });

    }
    this.getURL = function() {
      var url = this.host + this.endpoint;
      if (this.apiargs) {
        url += (url.indexOf('?') == -1 ? '?' : '&') + elation.utils.encodeURLParams(this.apiargs);
      }
      return url;
    }
    this.load = function() {
      if (this.loading) {
        this.cancel();
      }
      this.loading = true;
      var url = this.getURL();
      elation.events.fire({type: "collection_load_begin", element: this});
      this.xhr = elation.net.get(url, this.apiargs, { callback: elation.bind(this, function(d) { this.clear(); this.processResponse(d); }) });
    }
    this.clear = function() {
      if (this.data) {
        this.data.items.splice(0, this.items.length);
        this.data.count = 0;
      }
      elation.events.fire({type: "collection_clear", element: this});
    }
    this.cancel = function() {
      if (this.xhr) {
        console.log('stop it!', this.xhr);
        this.xhr.abort();
      }
    }
    this.append = function() {
      var url = this.getURL();
      elation.ajax.Get(url, this.apiargs, { callback: elation.bind(this, this.processResponse) });
    }
    this.getitems = function() {
      if (!this.data) {
        this.data = { items: [], count: 0 };
        this.load();
      }
      return this.data.items;
    }
    this.getlength = function() {
      if (!this.data) {
        this.data = { items: [], count: 0 };
        this.load();
      }
      return this.data.count;
    }
    this.processResponse = function(data, args) {
      var newdata = this.transformData(this.parseData(data));
      if (!this.data) {
        this.data = { items: [], count: 0 };
      }
      if (newdata.items) {
        Array.prototype.push.apply(this.data.items, newdata.items);
      }
      if (newdata.count) {
        this.data.count = newdata.count;
      }
      this.loading = false;
      elation.events.fire({type: "collection_load", element: this});
    }
    this.parseData = function(data) {
      return data;
    }
    this.transformData = function(data) {
      var transformed = {};
      if (this.datatransform.items) {
        transformed.items = this.datatransform.items(data);
      } else {
        transformed.items = data;
      }
      if (this.datatransform.count) {
        transformed.count = this.datatransform.count(data);
      } else {
        transformed.count = (transformed.items ? transformed.items.length : 0);
      }
      return transformed;
    }
  }, elation.collection.simple);

  /** 
   * JSON API-backed data collection
   * Provides a collection interface to a JSON REST API
   *
   * @class jsonapi
   * @augments elation.collection.api
   * @memberof elation.collection
   * @alias elation.collection.jsonapi
   *
   * @param {object} args
   * @param {string} args.host
   * @param {string} args.endpoint
   * @param {object} args.apiargs
   * @param {object} args.datatransform
   * @param {function} args.datatransform.items
   * @param {function} args.datatransform.count
   */
  elation.component.add("collection.jsonapi", function() {
    this.parseData = function(data) {
      return JSON.parse(data);
    }
  }, elation.collection.api);

  /** 
   * Custom data collection
   * Emits events when items are read, added, removed, etc. to allow arbitrary user-specified item backends
   * (For example, a collection which lists all the properties an object contains)
   *
   * @class custom
   * @augments elation.collection.simple
   * @memberof elation.collection
   * @alias elation.collection.custom
   *
   * @param {object} args
   */
  elation.component.add("collection.custom", function() {
    this.init = function() {
      elation.collection.custom.extendclass.init.call(this);
      if (this.args.items) {
        Object.defineProperties(this, {
          items: { get: this.args.items }
        });
      }
    }
  }, elation.collection.simple);
  /** 
   * Filter collection
   * Apply the specified filter to the parent list, and present it as its own collection
   *
   * @class filter
   * @augments elation.collection.simple
   * @memberof elation.collection
   * @alias elation.collection.filter
   *
   * @param {object} args
   * @param {elation.collection.simple} args.parent List to filter
   * @param {function} args.filterfunc Callback function for filtering list 
   */
  elation.component.add("collection.filter", function() {
    this.init = function() {
      elation.collection.filter.extendclass.init.call(this);

      this.parent = this.args.parent;
      this.filterfunc = this.args.filterfunc;

      Object.defineProperties(this, {
        items: { get: this.getfiltereditems }
      });
      // TODO - attach events to the parent, so we can respond to its events and emit our own as necessary
    }
    this.getfiltereditems = function() {
      var items = this.parent.items;
      var filtered = [];
      for (var i = 0; i < items.length; i++) {
        if (this.filterfunc(items[i])) {
          filtered.push(items[i]);
        }
      }
      return filtered;
    }
    this.update = function() {
      elation.events.fire({type: "collection_load", element: this});
    }
  }, elation.collection.simple);
});
