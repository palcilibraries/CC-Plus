  <template>
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
      <div class="container">
        <a class="navbar-brand" href="/">CC+</a>
        <div id="navbarSupportedContent" class="collapse navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="navbar-nav mr-auto">
              <div v-for="item in navList">
                <li v-if="item.children && (item.role=='All' || manager)" class="nav-item dropdown">
                  <a id="navbarDropdown" class="nav-link dropdown-toggle" :href="item.url" :title="item.name"
                     role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                     {{ item.name }}<span class="caret"></span>
                  </a>
                  <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                    <div v-if="item.role=='All' || manager">
                      <div v-for="{ url, name, index, target } in item.children" :key="index">
                        <a class="dropdown-item":href="url" :title="name" :target="target">{{ name }}</a>
                      </div>
                    </div>
                  </div>
                </li>
                <li v-else-if="item.role=='All' || manager" class="nav-item" >
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
</template>

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
              { url: "/", name: "Home", role: "All" },
              { url: "/reports", name: "Reports", role: "All" },
              {
                url: "#",
                name: "Settings",
                role: "Manager",
                children: [
                  {
                    url: "/users",
                    name: "Users",
                    role: "Manager",
                  },
                  {
                      url: "/providers",
                      name: "Providers",
                      role: "Manager",
                  },
                  {
                      url: "/institutions",
                      name: "Institutions",
                      role: "Manager",
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
    mounted() {
        this.profile_url = "/users/"+this.user["id"]+"/edit";
        console.log('Navbar Component mounted.');
    }
}
</script>

<style>
</style>
