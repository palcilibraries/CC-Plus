  <template>
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
      <div class="container">
        <a class="navbar-brand" href="/">CC+</a>
        <div id="navbarSupportedContent" class="collapse navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="navbar-nav mr-auto">
              <div v-for="item in navList">
                <li v-if="item.children && (item.role=='All' || is_manager)" class="nav-item dropdown">
                  <a id="navbarDropdown" class="nav-link dropdown-toggle" :href="item.url" :title="item.name"
                     role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                     {{ item.name }}<span class="caret"></span>
                  </a>
                  <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                    <div v-for="child in item.children">
                      <li v-if="child.role=='All' || is_manager">
                        <a class="dropdown-item":href="child.url" :title="child.name">{{ child.name }}</a>
                      </li>
                    </div>
                  </div>
                </li>
                <li v-else-if="item.role=='All' || is_manager" class="nav-item" >
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
                        <div v-if="is_manager">
                            <a class="dropdown-item" href="/admin">Dashboard</a>
                        </div>
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
            access: { type:String, default: '' },
            viewer: { type:Number, default: 0 },
    },
    data() {
        return {
            profile_url: '',
            navList: [
              { url: "/", name: "Home", role: "All" },
              // { url: "/reports", name: "Reports", role: "All" },
              {
                url: "#",
                name: "Reports",
                role: "All",
                children: [
                  {
                    url: "/reports",
                    name: "List",
                    role: "Manager",
                  },
                  {
                    url: "/reports/create",
                    name: "Create",
                    role: "All",
                  },
                  {
                    url: "/reports/export",
                    name: "Export",
                    role: "All",
                  }
                ]
              },
              {
                url: "#",
                name: "Settings",
                role: "All",
                children: [
                  {
                    url: "/users",
                    name: "Users",
                    role: "Manager",
                  },
                  {
                      url: "/providers",
                      name: "Providers",
                      role: "All",
                  },
                  {
                      url: "/institutions",
                      name: "Institutions",
                      role: "All",
                  },
                  {
                      url: "/institutiongroups",
                      name: "Groups",
                      role: "Manager",
                  }
                ]
              },
              {
                url: "#",
                name: "Activity",
                role: "All",
                children: [
                  {
                    url: "/harvestlogs",
                    name: "Harvests",
                    role: "All",
                  },
                  {
                    url: "/alerts",
                    name: "Alerts",
                    role: "All",
                  }
                ]
              },
            ]
        }
    },
    computed: {
      ...mapGetters(['is_manager'])
    },
    mounted() {
        this.$store.dispatch('updateAccess', this.access);
        this.$store.dispatch('updateUserInst', this.user["inst_id"]);
        this.profile_url = "/users/"+this.user["id"]+"/edit";
        if (this.viewer) {
            this.$store.dispatch('updateAccess', "Viewer");
        }
        this.$store.dispatch('updateAccess', this.access);

        console.log('Navbar Component mounted.');
    }
}
</script>

<style>
</style>
