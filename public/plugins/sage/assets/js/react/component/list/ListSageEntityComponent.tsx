import { createRoot } from "react-dom/client";
import React from "react";
import { ListSageEntityFilterComponent } from "./ListSageEntityFilterComponent";
import { ListSageEntityPagingComponent } from "./ListSageEntityPagingComponent";
import { ListSageEntityTableComponent } from "./ListSageEntityTableComponent";
import {
  FilterShowFieldInterface,
  FilterTypeInterface,
} from "../../../interface/ListSageEntityInterface";
import { BrowserRouter, useSearchParams } from "react-router-dom";
import { TOKEN } from "../../../token";

const siteUrl = $(`[data-${TOKEN}-site-url]`).attr(`data-${TOKEN}-site-url`);
const wpnonce = $(`[data-${TOKEN}-nonce]`).attr(`data-${TOKEN}-nonce`);
let realSearch = "";

type State = {
  filterTypes: FilterTypeInterface;
  filterFields: FilterShowFieldInterface[];
  showFields: FilterShowFieldInterface[];
  hideFields: string[];
  sageEntityName: string;
  mandatoryFields: string[];
  paginationRange: number[];
  perPage: string | number | undefined | null;
};

export interface ResultTableInterface {
  totalCount: number;
  items: any[];
}

export const ListSageEntityComponent: React.FC<State> = ({
  filterTypes,
  filterFields,
  showFields,
  hideFields,
  sageEntityName,
  mandatoryFields,
  paginationRange,
  perPage,
}) => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [result, setResult] = React.useState<ResultTableInterface | undefined>(
    undefined,
  );
  const [searching, setSearching] = React.useState<boolean>(false);

  const search = async () => {
    const params = new URLSearchParams(searchParams);
    params.delete("page");
    const stringParams = params.toString();
    realSearch = stringParams;
    setSearching(true);
    const response = await fetch(
      siteUrl +
        `/index.php?rest_route=${encodeURIComponent(`/${TOKEN}/v1/search/sage-entity-menu/${sageEntityName}`)}&${stringParams}&_wpnonce=${wpnonce}`,
    );
    if (response.ok) {
      if (realSearch === stringParams) {
        setResult(await response.json());
        setSearching(false);
      }
    } else {
      // todo toast r
      setSearching(false);
    }
  };

  React.useEffect(() => {
    search();
  }, [searchParams]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <>
      <div className="tablenav top">
        <ListSageEntityFilterComponent
          filterFields={filterFields}
          filterTypes={filterTypes}
          hideFields={hideFields}
          showFields={showFields}
        />
        <ListSageEntityPagingComponent
          result={result}
          paginationRange={paginationRange}
          defaultPerPage={Number(perPage)}
        />
        <br className="clear" />
      </div>
      <ListSageEntityTableComponent
        hideFields={hideFields}
        showFields={showFields}
        sageEntityName={sageEntityName}
        mandatoryFields={mandatoryFields}
        result={result}
        searching={searching}
      />
    </>
  );
};

const doms = document.querySelectorAll("[data-list-entity]");
doms.forEach((dom) => {
  const sageEntityName = dom.getAttribute("data-list-entity");
  const root = createRoot(dom.querySelector("[data-list-entity-content]"));
  root.render(
    <BrowserRouter>
      <ListSageEntityComponent
        sageEntityName={sageEntityName}
        filterTypes={JSON.parse(
          dom
            .querySelector("[data-filtertypes]")
            .getAttribute("data-filtertypes"),
        )}
        filterFields={JSON.parse(
          dom
            .querySelector("[data-filterfields]")
            .getAttribute("data-filterfields"),
        )}
        showFields={JSON.parse(
          dom
            .querySelector("[data-showfields]")
            .getAttribute("data-showfields"),
        )}
        paginationRange={JSON.parse(
          dom
            .querySelector("[data-paginationrange]")
            .getAttribute("data-paginationrange"),
        )}
        hideFields={JSON.parse(
          dom
            .querySelector("[data-hidefields]")
            .getAttribute("data-hidefields"),
        )}
        mandatoryFields={JSON.parse(
          dom
            .querySelector("[data-mandatoryfields]")
            .getAttribute("data-mandatoryfields"),
        )}
        perPage={dom
          .querySelector("[data-perpage]")
          .getAttribute("data-perpage")}
      />
    </BrowserRouter>,
  );
});
