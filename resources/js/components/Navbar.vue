<template>
    <nav class="navbar navbar-expand navbar-light">
        <div id="navbarSupportedContent" class="collapse navbar-collapse navbar-items">
            <!-- Left Side Of Navbar -->
            <ul class="navbar-nav mr-auto">
              <div v-for="item in navList">
                <li v-if="isVisible(item) && item.name == 'Home'">
                  <a class="nav-link" :href="homeUrl" :title="item.name">
                    <v-icon title="Home" alt="Home" class="align-center">mdi-home</v-icon>
                  </a>
                </li>
                <li v-else-if="isVisible(item) && item.children" class="nav-item dropdown py-1">
                  <a id="navbarDropdown" class="nav-link dropdown-toggle" :href="item.url" :title="item.name"
                     role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                     {{ item.name }}<span class="caret"></span>
                  </a>
                  <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                    <div v-for="child in item.children">
                      <li v-if="isVisible(child)">
                        <a class="dropdown-item":href="child.url" :title="child.name">{{ child.name }}</a>
                      </li>
                    </div>
                  </div>
                </li>
                <li v-else-if="isVisible(item)" class="nav-item" >
                  <a class="nav-link" :href="item.url" :title="item.name">{{ item.name }}</a>
                </li>
              </div>
            </ul>
            <!-- Right Side Of Navbar -->
            <ul class="navbar-nav ml-auto">
              <li v-if="is_globaladmin && ccp_key!=''" class="nav-item py-1">
                <v-select :items="consortia" v-model="cur_key" label="Instance" item-text="name"
                          item-value="ccp_key" @change="changeInstance" dense outlined hide-details
                ></v-select>
              </li>
                <!-- Authentication Links -->
              <li v-if="this.user['id']==0" class="nav-item">
                <a class="nav-link" href="/login">Login</a>
              </li>
              <li v-else class="nav-item dropdown py-1">
                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                      data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      {{ mutable_user["name"] }}<span class="caret"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                  <li v-if="!is_globaladmin" class="nav-item" >
                    <!-- <a class="dropdown-item" :href="profile_url">Profile</a> -->
                    <a class="dropdown-item" @click="userDialog=true">Profile</a>
                  </li>
                  <li class="nav-item" >
                    <a class="dropdown-item" href="/logout" onclick="event.preventDefault();
                              document.getElementById('logout-form').submit();">Logout</a>
                    <form id="logout-form" action="/logout" method="POST" style="display: none;">
                      @csrf
                    </form>
                  </li>
                </div>
              </li>
            </ul>
        </div>
        <v-dialog v-model="userDialog" content-class="ccplus-dialog">
          <user-dialog dtype="profile" :user="mutable_user" @user-complete="userDialogDone"></user-dialog>
        </v-dialog>
    </nav>
</template>

<script>
import { mapGetters } from 'vuex'
export default {
    props: {
        user: { type:Object, default: () => {} },
        consortia: { type:Array, default: () => [] },
        ccp_key: { type:String, default: '' },
    },
    data() {
        return {
            profile_url: '',
            cur_key: '',
            homeUrl: "/",
            navList: [
              { url: "/", name: "Home", role: "All" },
              { url: "/global/home", name: "Global Admin", role: "GlobalAdmin" },
              { url: "/consoadmin", name: "Consortium Admin", role: "Admin"},
              { url: "#", name: "My Institution", role: "ManagerOnly"},
              { url: "/harvests", name: "Harvesting", role: "All"},
              {
                url: "#",
                name: "Reports",
                role: "All",
                children: [
                  {
                    url: "/my-reports",
                    name: "My Reports",
                    role: "All",
                  },
                  {
                    url: "/reports/create",
                    name: "Create",
                    role: "All",
                  },
                ]
              },
            ],
            userDialog: false,
            mutable_user: {...this.user},
        }
    },
    methods: {
      isVisible(item) {
        if (item.role == 'All') return true;
        if (item.role == 'ManagerOnly') return (this.is_manager && !this.is_admin);
        if (this.is_globaladmin) return true;
        if (this.is_admin) {
            return (item.role != 'GlobalAdmin');
        } else if (this.is_manager) {
            return (item.role != 'Admin' && item.role != 'GlobalAdmin');
        } else if (this.is_viewer) {
            return (item.role == 'Viewer');
        }
        return false;
      },
      changeInstance (event) {
          var _args = {'ccp_key' : this.cur_key};
          axios.post('/change-instance', _args)
               .then((response) => {
                  if (response.data.result == 'success') {
                      console.log("Consortium instance changed to: "+this.cur_key);
                      // Reload whatever page we're on from the server
                      window.location.reload(true);
                  } else {
                      console.log("Change instance failed! : "+response.data.result);
                  }
              })
             .catch(error => {});
      },
      userDialogDone ({ result, msg, user, new_inst }) {
        if (result == 'Success') {
          this.mutable_user.name = user.name;   // the only field that navbar needs to update...
        }
        this.userDialog = false;
      },
    },
    computed: {
      ...mapGetters(['is_manager','is_admin','is_viewer','is_globaladmin'])
    },
    mounted() {
        // Get user's max role
        var max_id = 0;
        var max_role = '';
        this.user.roles.forEach((role) => {
            if (role.id > max_id) {
                max_id = role.id;
                max_role = role.name;
            }
            // Set this explicitly since Viewer_ID > Manager_ID (otherwise when exiting loop and
            // setting access to max, would miss this if manager.AND.viewer)
            if (role.name == "Manager") this.$store.dispatch('updateAccess', "Manager");
        });
        this.$store.dispatch('updateAccess', max_role);
        this.$store.dispatch('updateUserInst', this.user["inst_id"]);
        this.profile_url = "/users/"+this.user["id"]+"/edit";
        if (this.is_globaladmin) {
            this.consortia.push({'ccp_key': 'con_template', 'name': 'Template'});
            if (this.consortia.some(con => con.ccp_key == this.ccp_key)) this.cur_key = this.ccp_key;
        }
        // Set homeUrl based on role
        var _idx = null;
        if (this.is_globaladmin) {
            this.homeUrl = "/global/home";
        } else if (this.is_admin) {
            this.homeUrl = "/consoadmin";
        } else if (this.is_manager) {
            this.homeUrl = "/institutions/"+this.user.inst_id;
            _idx = this.navList.findIndex(nav => nav.name == "My Institution");
            this.navList[_idx].url = this.homeUrl;
        } else {  // Viewer abd un-priv users set to my-reports
            this.homeUrl = "/my-reports";
        }

        // Basic users see "Harvests" as a link, not a dropdown with children
        if (!this.is_admin && !this.is_manager && !this.is_viewer) {
        // } else if (!this.is_admin && !this.is_manager && !this.is_viewer) {
            _idx = this.navList.findIndex(nav => nav.name == "Harvests");
            this.navList[_idx].url = "/harvest/log";
            this.navList[_idx].children = null;
        }
        console.log('Navbar Component mounted.');
    }
}
</script>
