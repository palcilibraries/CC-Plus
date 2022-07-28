  <template>
    <nav class="navbar navbar-expand navbar-light bg-white shadow-sm">
      <div class="container">
        <!--<a class="navbar-brand" href="/">CC+</a>-->
		<a class="navbar-brand" href="/"><img src="/images/CC_Plus_Logo.png" alt="CC plus" height="50px" width="103px" /></a>
        <div id="navbarSupportedContent" class="collapse navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="navbar-nav mr-auto">
              <div v-for="item in navList">
                <li v-if="item.children && isVisible(item)" class="nav-item dropdown">
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
                <!-- Authentication Links -->
                <li v-if="this.user['id']==0" class="nav-item">
                    <a class="nav-link" href="/login">Login</a>
                </li>
                <li v-else class="nav-item dropdown">
                    <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        {{ user["name"] }}<span class="caret"></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" :href="profile_url">Profile</a>
                        <a class="dropdown-item" href="/logout"
                           onclick="event.preventDefault();
                                         document.getElementById('logout-form').submit();">Logout</a>
                        <form id="logout-form" action="/logout" method="POST" style="display: none;">
                            @csrf
                        </form>
                    </div>
                </li>
            </ul>
        </div>
      </div>
  </nav>
</template>

<script>
import { mapGetters } from 'vuex'
export default {
    props: {
        user: { type:Object, default: () => {} },
    },
    data() {
        return {
            profile_url: '',
            navList: [
              { url: "/", name: "Home", role: "All" },
              {
                url: "#",
                name: "Admin",
                role: "Admin",
                children: [
                  {
                    url: "/users",
                    name: "Users",
                    role: "Admin",
                  },
                  {
                    url: "/institutions",
                    name: "Institutions",
                    role: "Admin",
                  },
                  {
                    url: "/institutiongroups",
                    name: "Groups",
                    role: "Admin",
                  },
                  {
                    url: "/providers",
                    name: "Providers",
                    role: "Admin",
                  },
                ]
              },
              {
                url: "#",
                name: "Harvests",
                role: "All",
                children: [
                  {
                    url: "/harvestlogs/create",
                    name: "Manual Harvest",
                    role: "All",
                  },
                  {
                    url: "/harvestlogs/create",
                    name: "Scheduled",
                    role: "All",
                  },
                  {
                    url: "/harvestlogs",
                    name: "Logs",
                    role: "All",
                  },
                  {
                    url: "/alerts",
                    name: "Alerts",
                    role: "All",
                  },
                ]
              },
              {
                url: "#",
                name: "Reports",
                role: "All",
                children: [
                  {
                    url: "/reports/create",
                    name: "Configure",
                    role: "All",
                  },
                  {
                    url: "/reports",
                    name: "Types",
                    role: "All",
                  },
                ]
              },
            ]
        }
    },
    methods: {
      isVisible(item) {
        if (this.is_admin) return true;
        if (this.is_manager && (item.role != 'Admin')) return true;
        if (item.role == 'All') return true;
        return false;
      }
    },
    computed: {
      ...mapGetters(['is_manager','is_admin','is_viewer'])
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
        if (this.is_admin || this.is_viewer) {
            var idx1 = this.navList.findIndex(nav => nav.name == "Settings");
            var idx2 = this.navList[idx1].children.findIndex(nav => nav.url == '/institutions');
            this.navList[idx1].children[idx2].name = "Institutions";
        }

        console.log('Navbar Component mounted.');
    }
}
</script>

<style>
</style>
