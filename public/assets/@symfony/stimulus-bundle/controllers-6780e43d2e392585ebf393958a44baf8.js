import controller_0 from "../ux-chartjs/controller.js";
import controller_1 from "../ux-turbo/turbo_controller.js";
import controller_2 from "../../controllers/hello_controller.js";
import controller_3 from "../../controllers/irrigation_dashboard_controller.js";
export const eagerControllers = {"symfony--ux-chartjs--chart": controller_0, "symfony--ux-turbo--turbo-core": controller_1, "hello": controller_2, "irrigation-dashboard": controller_3};
export const lazyControllers = {"csrf-protection": () => import("../../controllers/csrf_protection_controller.js")};
export const isApplicationDebug = true;