  <template>
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
      <div class="container">
        <a class="navbar-brand" href="/">CC+</a>
        <div id="navbarSupportedContent" class="collapse navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="navbar-nav mr-auto">
              <div v-for="item in navList">
                <li v-if="item.children && (item.visibility=='All' || manager)" class="nav-item dropdown">
                  <a id="navbarDropdown" class="nav-link dropdown-toggle" :href="item.url" :title="item.name"
                     role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                     {{ item.name }}<span class="caret"></span>
                  </a>
                  <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                    <div v-if="item.visibility=='All' || manager">
                      <div v-for="{ url, name, index, target } in item.children" :key="index">
                        <a class="dropdown-item":href="url" :title="name" :target="target">{{ name }}</a>
                      </div>
                    </div>
                  </div>
                </li>
                <li v-else-if="item.visibility=='All' || manager" class="nav-item" >
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
                        <div v-if="manager">
                            <a class="dropdown-item" href="/admin">Admin Dashboard</a>
                            <a class="dropdown-item" href="/">Reports Dashboard</a>
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
</template> -->

<script>
export default {
    props: {
            user: { type:Object, default: () => {} },
            manager: { type:Number, default:0 },
    },
    data() {
        return {
            profile_url: '',
            navList: [
              { url: "/", name: "Home", visibility: "All" },
              { url: "/reports", name: "Reports", visibility: "All" },
              {
                url: "#",
                name: "Settings",
                visibility: "Manager",
                children: [
                  {
                    url: "/users",
                    name: "Users",
                    visibility: "Manager",
                  },
                  {
                      url: "/providers",
                      name: "Providers",
                      visibility: "Manager",
                  },
                  {
                      url: "/institutions",
                      name: "Institutions",
                      visibility: "Manager",
                  },
                  {
                      url: "/institutiongroups",
                      name: "Groups",
                      visibility: "Manager",
                  }
                ]
              },
              {
                url: "#",
                name: "Activity",
                visibility: "All",
                children: [
                  {
                    url: "/harvestlogs",
                    name: "Harvests",
                    visibility: "All",
                  },
                  {
                    url: "/alerts",
                    name: "Alerts",
                    visibility: "All",
                  }
                ]
              },
            ]
        }
    },
    mounted() {
        this.profile_url = "/users/"+this.user["id"]+"/edit";
        console.log('Provider Component mounted.');
    }
}
</script>

<style>
</style>

