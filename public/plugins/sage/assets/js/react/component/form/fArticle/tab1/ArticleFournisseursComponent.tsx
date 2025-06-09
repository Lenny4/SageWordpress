// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { FArtfournisseInterface } from "../../../../../interface/FArticleInterface";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../../../functions/getMetadata";
import {
  FormInterface,
  ResponseTableLineItemInterface,
  TableLineItemInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import { AfPrincipalInput } from "../inputs/AfPrincipalInput";
import { FormContentComponent } from "../../FormContentComponent";
import {
  createFormContent,
  handleFormIsValid,
} from "../../../../../functions/form";
import { FormInput } from "../../fields/FormInput";
import {
  numberValidator,
  stringValidator,
} from "../../../../../functions/validator";
import { TOKEN } from "../../../../../token";
import { FComptetInterface } from "../../../../../interface/FComptetInterface";
import { ResultTableInterface } from "../../../list/ListSageEntityComponent";

let translations: any = getTranslations();
const siteUrl = $(`[data-${TOKEN}-site-url]`).attr(`data-${TOKEN}-site-url`);
const wpnonce = $(`[data-${TOKEN}-nonce]`).attr(`data-${TOKEN}-nonce`);

const articleMeta: MetadataInterface[] = JSON.parse(
  $(`[data-${TOKEN}-product]`).attr(`data-${TOKEN}-product`) ?? "[]",
);

export const ArticleFournisseursComponent = React.forwardRef((props, ref) => {
  const prefix = "fArtfournisses";
  const [fArtfournisses, setFArtfournisses] = React.useState<
    FArtfournisseInterface[]
  >(getListObjectSageMetadata(prefix, articleMeta, "ctNum"));
  const getSelectedCtNum = (): string => {
    return (
      fArtfournisses.find((x) => x.afPrincipal.toString() === "1")?.ctNum ?? ""
    );
  };
  const [selectedCtNum, setSelectedCtNum] =
    React.useState<string>(getSelectedCtNum());
  const onAfPrincipalChanged: TriggerFormContentChanged = (name, newValue) => {
    setSelectedCtNum(newValue);
  };

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
              headers: [
                translations.words.supplier,
                translations.words.main,
                translations.words.supplierRef,
                translations.words.buyPrice,
              ],
              items: fArtfournisses.map(
                (fArtclient): TableLineItemInterface => {
                  return {
                    item: fArtclient,
                    identifier: fArtclient.ctNum,
                    lines: [
                      {
                        Dom: <>{fArtclient.ctNum}</>,
                      },
                      {
                        Dom: (
                          <AfPrincipalInput
                            selectedCtNum={selectedCtNum}
                            ctNum={fArtclient.ctNum}
                            onAfPrincipalChangedParent={onAfPrincipalChanged}
                            ref={React.createRef()}
                          />
                        ),
                      },
                      {
                        field: {
                          name:
                            prefix + "[" + fArtclient.ctNum + "].afRefFourniss",
                          DomField: FormInput,
                          hideLabel: true,
                          initValues: {
                            value: getSageMetadata(
                              prefix +
                                "[" +
                                fArtclient.ctNum +
                                "].afRefFourniss",
                              articleMeta,
                              fArtclient.afRefFourniss,
                            ),
                            validator: {
                              functionName: stringValidator,
                              params: {
                                maxLength: 19,
                                isReference: true,
                              },
                            },
                          },
                        },
                      },
                      {
                        field: {
                          name: prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                          DomField: FormInput,
                          type: "number",
                          hideLabel: true,
                          initValues: {
                            value: getSageMetadata(
                              prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                              articleMeta,
                              fArtclient.afPrixAch,
                            ),
                            validator: {
                              functionName: numberValidator,
                              params: {
                                canBeEmpty: true,
                              },
                            },
                          },
                        },
                      },
                    ],
                  };
                },
              ),
              removeItem: (fComptet: FComptetInterface) => {
                setFArtfournisses((v) => {
                  const r = v.filter(
                    (fArtfourniss) => fArtfourniss.ctNum !== fComptet.ctNum,
                  );
                  if (
                    r.length > 0 &&
                    !r.find((x) => x.afPrincipal.toString() === "1")
                  ) {
                    r[0].afPrincipal = 1;
                  }
                  return r;
                });
              },
              add: {
                table: {
                  headers: [translations.words.supplier],
                  search: (item: FComptetInterface, search: string) => {
                    return (
                      item.ctNum.toLowerCase().includes(search.toLowerCase()) ||
                      item.ctIntitule
                        .toLowerCase()
                        .includes(search.toLowerCase()) ||
                      item.ctContact
                        .toLowerCase()
                        .includes(search.toLowerCase())
                    );
                  },
                  addItem: (fComptet: FComptetInterface) => {
                    setFArtfournisses((v) => {
                      let principal = 0;
                      if (!v.find((x) => x.afPrincipal.toString() === "1")) {
                        principal = 1;
                      }
                      return [
                        ...v,
                        {
                          ctNum: fComptet.ctNum,
                          afPrincipal: principal,
                          afRefFourniss: "",
                          afPrixAch: 0,
                        },
                      ];
                    });
                  },
                  localStorageItemName: "fFournisseurs",
                  items: async (
                    search: string = "",
                    cacheResponse: ResultTableInterface = undefined,
                  ): Promise<ResponseTableLineItemInterface> => {
                    const responseToData = (
                      thisResponse: ResultTableInterface,
                    ) => {
                      return thisResponse.items.map(
                        (fComptet: FComptetInterface): TableLineItemInterface => {
                          return {
                            item: fComptet,
                            identifier: fComptet.ctNum,
                            lines: [
                              {
                                Dom: (
                                  <>
                                    {fComptet.ctNum} {fComptet.ctIntitule}
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
                      "filter_field[0]": "ctType",
                      "filter_type[0]": "eq",
                      "filter_value[0]": "1",

                      "filter_field[1]": "ctNum",
                      "filter_type[1]": "contains",
                      "filter_value[1]": search,

                      where_condition: "and",
                      per_page: "100",
                    });
                    const response = await fetch(
                      siteUrl +
                        `/index.php?rest_route=${encodeURIComponent(`/${TOKEN}/v1/search-entities/fComptets`)}&${params}&_wpnonce=${wpnonce}`,
                    );
                    if (response.ok) {
                      const data: ResultTableInterface = await response.json();
                      return {
                        items: responseToData(data),
                        response: data,
                      };
                    } else {
                      // todo toast r
                    }
                    return {
                      items: null,
                      response: null,
                    };
                  },
                },
              },
            },
          },
        ],
      }),
    };
  };
  const [form, setForm] = React.useState<FormInterface>(getForm());

  useImperativeHandle(ref, () => ({
    async isValid(): Promise<boolean> {
      return await handleFormIsValid(form.content);
    },
  }));

  React.useEffect(() => {
    setForm(getForm());
  }, [selectedCtNum, fArtfournisses]); // eslint-disable-line react-hooks/exhaustive-deps

  React.useEffect(() => {
    setSelectedCtNum(getSelectedCtNum());
  }, [fArtfournisses]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <FormContentComponent content={form.content} transPrefix="fArticles" />
  );
});
