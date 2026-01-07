// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { Tooltip } from "@mui/material";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import {
  FArtglosseInterface,
  FGlossaireInterface,
} from "../../../../../interface/FArticleInterface";
import { getListObjectSageMetadata } from "../../../../../functions/getMetadata";
import {
  FormInterface,
  FormValidInterface,
  ResponseTableLineItemInterface,
  TableLineItemInterface,
} from "../../../../../interface/InputInterface";
import { FormContentComponent } from "../../FormContentComponent";
import {
  createFormContent,
  handleFormIsValid,
} from "../../../../../functions/form";
import { FormInput } from "../../fields/FormInput";
import { TOKEN } from "../../../../../token";
import { ResultTableInterface } from "../../../list/ListSageEntityComponent";
import { GlossaireDomaineTypeEnum } from "../../../../../enum/GlossaireDomaineTypeEnum";
import { FilterInterface } from "../../resource/ResourceFilterComponent";

let translations: any = getTranslations();
const siteUrl = $(`[data-${TOKEN}-site-url]`).attr(`data-${TOKEN}-site-url`);
const wpnonce = $(`[data-${TOKEN}-nonce]`).attr(`data-${TOKEN}-nonce`);

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

const fArticle: any = JSON.parse(
  $(`[data-${TOKEN}-farticle]`).attr(`data-${TOKEN}-farticle`) ?? "{}",
);

export const ArticleGlossairesComponent = React.forwardRef((props, ref) => {
  const prefix = "fArtglosses";
  const [fArtglosses, setFArtglosses] = React.useState<FArtglosseInterface[]>(
    () => {
      const result: FArtglosseInterface[] = getListObjectSageMetadata(
        prefix,
        articleMeta,
        "glNo",
      );
      for (const fArtglosse of result) {
        fArtglosse.glNoNavigation = {
          glText: "",
          glIntitule: "[ERR]",
          glNo: fArtglosse.glNo,
          glDomaine: 0, // 0 -> Article, 1 => document
          ...(fArticle?.fArtglosses?.[fArtglosse.glNo]?.glNoNavigation ?? {}),
        };
      }
      return result;
    },
  );

  const getForm = (): FormInterface => {
    return {
      content: createFormContent({
        props: {
          container: true,
          spacing: 1,
          sx: { p: 1 },
        },
        children: [
          {
            props: {
              size: { xs: 12 },
            },
            table: {
              headers: ["", translations.words.intitule, ""],
              removeItem: (fGlossaire: FGlossaireInterface) => {
                setFArtglosses((v) => {
                  return v.filter(
                    (fArtglosse) =>
                      fArtglosse.glNo.toString() !== fGlossaire.glNo.toString(),
                  );
                });
              },
              add: {
                table: {
                  headers: [translations.words.intitule],
                  addItem: (fGlossaire: FGlossaireInterface) => {
                    setFArtglosses((v) => {
                      return [
                        ...v,
                        {
                          glNo: fGlossaire.glNo,
                          glNoNavigation: fGlossaire,
                        },
                      ];
                    });
                  },
                  search: (item: FGlossaireInterface, search: string) => {
                    return (
                      item.glIntitule
                        .toLowerCase()
                        .includes(search.toLowerCase()) ||
                      item.glText.toLowerCase().includes(search.toLowerCase())
                    );
                  },
                  localStorageItemName: "fGlossaires",
                  items: async (
                    search: string = "",
                    page: number = 1,
                    cacheResponse: ResultTableInterface = undefined,
                  ): Promise<ResponseTableLineItemInterface> => {
                    const responseToData = (
                      thisResponse: ResultTableInterface,
                    ) => {
                      return thisResponse.items.map(
                        (
                          fGlossaire: FGlossaireInterface,
                        ): TableLineItemInterface => {
                          return {
                            item: fGlossaire,
                            identifier: fGlossaire.glNo.toString(),
                            lines: [
                              {
                                Dom: (
                                  <>
                                    {fGlossaire.glIntitule} {fGlossaire.glText}
                                  </>
                                ),
                              },
                            ],
                          };
                        },
                      );
                    };
                    if (cacheResponse) {
                      return {
                        items: responseToData(cacheResponse),
                        response: cacheResponse,
                      };
                    }
                    const params = new URLSearchParams({
                      filter: encodeURIComponent(
                        JSON.stringify({
                          condition: "and",
                          conditionValues: "or",
                          values: [
                            {
                              field: "glText",
                              condition: "contains",
                              value: search,
                            },
                            {
                              field: "glIntitule",
                              condition: "contains",
                              value: search,
                            },
                          ],
                          subFilter: {
                            condition: "and",
                            values: [
                              {
                                field: "glDomaine",
                                condition: "eq",
                                value:
                                  GlossaireDomaineTypeEnum.GlossaireDomaineTypeArticle,
                              },
                            ],
                          },
                        } as FilterInterface),
                      ),
                      paged: page.toString(),
                      per_page: "100",
                    });
                    const response = await fetch(
                      siteUrl +
                        `/index.php?rest_route=${encodeURIComponent(`/${TOKEN}/v1/search-entities/fGlossaires`)}&${params}&_wpnonce=${wpnonce}`,
                    );
                    if (response.ok) {
                      const data: ResultTableInterface = await response.json();
                      return {
                        items: responseToData(data),
                        response: data,
                      };
                    }
                    return Promise.reject(
                      new Error(
                        `${response.status} ${response.statusText}`,
                      ),
                    );
                  },
                },
              },
              items: fArtglosses.map((fArtglosse): TableLineItemInterface => {
                const fGlossaire = fArtglosse.glNoNavigation;
                return {
                  item: fArtglosse,
                  identifier: fArtglosse.glNo.toString(),
                  lines: [
                    {
                      field: {
                        name: prefix + "[" + fGlossaire.glNo + "][glNo]",
                        DomField: FormInput,
                        type: "hidden",
                        hideLabel: true,
                        initValues: {
                          value: fGlossaire.glNo,
                        },
                      },
                    },
                    {
                      Dom: <span>{fGlossaire.glIntitule}</span>,
                    },
                    {
                      Dom: (
                        <Tooltip title={fGlossaire.glText} arrow>
                          <span>
                            {fGlossaire.glText.length > 102
                              ? fGlossaire.glText.slice(0, 102) + "..."
                              : fGlossaire.glText}
                          </span>
                        </Tooltip>
                      ),
                    },
                  ],
                };
              }),
            },
          },
        ],
      }),
    };
  };

  const [form, setForm] = React.useState<FormInterface>(getForm());

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<FormValidInterface> {
      return await handleFormIsValid(form.content);
    },
  }));

  React.useEffect(() => {
    setForm(getForm());
  }, [fArtglosses]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <FormContentComponent content={form.content} transPrefix="fArticles" />
  );
});
