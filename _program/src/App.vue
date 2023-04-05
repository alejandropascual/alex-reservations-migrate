<template>
  <div class="p-6">

    <div class="text-xl">
      MIGRATE BOOKINGS FROM 'Quick Restaurant Reservations' to 'Alex Reservations Plugin'
    </div>

    <div class="mt-6">
      <div class="text-lg mb-2">Map the restaurants</div>
      <div>For importing the bookings you have to map each restaurant.</div>
      <div>Only restaurants mapped will import the bookings.</div>

      <!-- MAP RESTAURANTS -->
      <div v-if="restaurants.length" class="mt-2 p-2 bg-white">
        <table>
          <thead>
            <tr>
              <th class="p-1 text-left">QRR Restaurant -> </th>
              <th class="p-1 text-left">Alex Restaurant</th>
              <th class="p-1 text-left">Shift</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(restaurant,index) in restaurants">
              <td class="p-1">{{ restaurant.title}} ({{ restaurant.id}})</td>
              <td class="p-1" :class="restaurant.alex_id == 0 ? 'bg-red-300': 'bg-green-300'">
                <select v-model="restaurant.alex_id">
                  <option value="0">- no restaurant -</option>
                  <option v-for="item in alex_restaurants" :value="item.id">{{ item.name }} ({{item.id}})</option>
                </select>
              </td>
              <td class="p-1" :class="restaurant.shift_id == 0 ? 'bg-red-300': 'bg-green-300'">
                <select v-model="restaurant.shift_id" >
                  <option value="0">- no shift -</option>
                  <option v-for="item in shifts_for_restaurant(restaurant.alex_id)" :value="item.id">{{ item.name }} ({{item.id}})</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>


      <div class="text-lg mb-2 mt-4">List of bookings</div>

      <div v-if="!processing">
        <button v-if="!loading" @click.prevent="loadBookings" class="button button-primary">Load bookings</button>
        <div v-if="loading">...loading...</div>
      </div>

      <div v-if="bookings.length" class="mt-2 p-2 border-t border-b border-slate-500 max-h-[600px] bg-white overflow-y-scroll">
        <table>
          <thead>
            <tr>
              <th></th>
              <th class="p-1 text-left">Restaurant</th>
              <th class="p-1 text-left">ID</th>
              <th class="p-1 text-left">Name</th>
              <th class="p-1 text-left">Date Time</th>
              <th class="p-1 text-left">Email</th>
              <th class="p-1 text-left">Phone</th>
              <th>New restaurant</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(booking,index) in bookings" :class="isFutureBooking(booking) ? 'bg-green-200' : 'bg-slate-100'">
              <td class="p-1">{{ isFutureBooking(booking) ? 'FUTURE' : '-' }}</td>
              <td class="p-1">{{ booking.restaurant}} ({{ booking.rest_id}})</td>
              <td class="p-1">{{ booking.id}} </td>
              <td class="p-1">{{ booking.title}} </td>
              <td class="p-1">{{ booking.date}} </td>
              <td class="p-1">{{ booking.email}} </td>
              <td class="p-1">{{ booking.phone}} </td>
              <td class="p-1 font-bold">
                <div v-if="isMapped(booking)" class="text-black font-bold">{{ restaurantMapped(booking.rest_id).name }}</div>
                <div v-else class="text-red-400">-not mapped-</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col gap-2 mt-4" v-if="bookings.length">
        <label>
          <input type="checkbox" name="delete_all_bookings" v-model="checkDelete"/>
          <span>Delete All Bookings</span>
        </label>

        <label>
          <input type="checkbox" name="import_past_bookings" v-model="checkImportPast"/>
          <span>Import Past Bookings</span>
        </label>

        <label>
          <input type="checkbox" name="import_future_bookings" v-model="checkImportFuture"/>
          <span>Import Future Bookings</span>
        </label>

        <div>
          <button v-if="!processing" @click.prevent="execute" class="button button-primary">EXECUTE</button>
          <div v-if="processing" class="text-xl font-bold">{{ processCount }} / {{ bookings.length }}</div>
        </div>

        <div class="text-sm">
          <div>Only bookings with the restaurant mapped can be imported.</div>
          <div>It will create clients based on the booking data (email).</div>
          <div>If a booking has already being imported will not be imported again.</div>
        </div>

      </div>

    </div>


  </div>
</template>

<script>
export default {
  data() {
    return {
      ajaxurl: window.ajaxurl,
      loading: false,
      restaurants: [],
      shifts: [],
      bookings: [],
      checkDelete: false,
      checkImportPast: false,
      checkImportFuture: false,
      processing: false,
      processCount: 0
    }
  },

  created(){
    this.loadRestaurants()
    this.loadBookings()
  },

  methods: {
    loadRestaurants() {

      this.loading = true

      let data = {
        action: 'alexm_get_restaurants'
      }

      axios.get(this.ajaxurl, {params: data})
          .then(response => {
            if (response.data.success) {
              this.restaurants = response.data.data.restaurants
              this.alex_restaurants = response.data.data.alex_restaurants
              this.shifts = response.data.data.shifts
            }
            this.loading = false
          })
          .catch(error => {
            this.loading = false
          })
    },

    loadBookings(){

      this.loading = true

      let data = {
        action: 'alexm_get_future_bookings'
      }

      axios.get(this.ajaxurl, {params: data})
        .then(response => {
          if (response.data.success) {
            //console.log(response.data.data.bookings)
            this.bookings = response.data.data.bookings
          }
          this.loading = false
        })
        .catch(error => {
          this.loading = false
        })

    },

    isFutureBooking(booking) {
      //let dateFormat = DateTime.fromFormat('yyyy-MM-dd HH:mm:ss', booking.date).toFormat('yyyy-MM-dd')
      if (!booking.date) return false
      let dateFormat = booking.date.substring(0,10)
      let now = DateTime.now().toFormat('yyyy-MM-dd')
      return dateFormat >= now
    },

    isMapped(booking) {
      let rest_id = booking.rest_id
      for (let i = 0; i < this.restaurants.length; i++){
        if (this.restaurants[i].id == booking.rest_id) {
          return this.restaurants[i].alex_id > 0
        }
      }
      return false
    },

    restaurantMapped(rest_id) {
      for (let i = 0; i < this.restaurants.length; i++){
        if (this.restaurants[i].id == rest_id) {
          let alex_id = this.restaurants[i].alex_id
          return this.getAlexRestaurant(alex_id)
        }
      }

      return null
    },

    restaurantMappedId(rest_id) {
      for (let i = 0; i < this.restaurants.length; i++){
        if (this.restaurants[i].id == rest_id) {
          return this.restaurants[i].alex_id
        }
      }

      return null
    },

    restaurantShiftMappedId(rest_id) {
      for (let i = 0; i < this.restaurants.length; i++){
        if (this.restaurants[i].id == rest_id) {
          return this.restaurants[i].shift_id
        }
      }

      return null
    },

    getAlexRestaurant(id) {
      for (let i = 0; i < this.alex_restaurants.length; i++){
        if (this.alex_restaurants[i].id == id){
          return this.alex_restaurants[i]
        }
      }
      return null
    },

    shifts_for_restaurant(alex_id) {
      let list = []

      for (let i = 0; i < this.shifts.length; i++){
        if (this.shifts[i].restaurant_id == alex_id) {
          list.push(this.shifts[i])
        }
      }

      return list;
    },

    async execute() {
      this.processCount = 0
      this.processing = true

      for (let i = 0; i < this.bookings.length; i++){
        await this.executeForBooking(this.bookings[i])
        this.$nextTick(() => {
          this.processCount = i+1
        })
      }

      this.loadBookings()
      this.processing = false
    },

    executeForBooking(booking) {
      let data = {
        action: 'alexm_process_booking',
        bookingId: booking.id,
        isFutureBooking: this.isFutureBooking(booking) ? 'yes' : 'no',
        alexRestaurantId: this.restaurantMappedId(booking.rest_id),
        alexShiftId: this.restaurantShiftMappedId(booking.rest_id),
        checkDelete: this.checkDelete ? 'yes' : 'no',
        checkImportPast: this.checkImportPast ? 'yes' : 'no',
        checkImportFuture: this.checkImportFuture ? 'yes' : 'no'
      }

      return axios.get(this.ajaxurl, {params: data})
          .then(response => {
            if (response.data.success) {

            }
          })
          .catch(error => {

          })
    }

  }
}
</script>
