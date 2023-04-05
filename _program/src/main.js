import { createApp } from 'vue'
import App from './App.vue'
import axios from "axios"
window.axios = axios
import _ from 'lodash'
window._ = _
import { DateTime } from "luxon";
window.DateTime = DateTime
import Swal from "sweetalert2";
window.Swal = Swal

let app = createApp(App).mount('#alexmigrate')
